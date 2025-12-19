<?php
$config = require __DIR__ . '/../../lib/bootstrap.php';

require_once __DIR__ . '/../../lib/Db.php';
require_once __DIR__ . '/../../lib/Response.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Csrf.php';
require_once __DIR__ . '/../../lib/Util.php';
require_once __DIR__ . '/../../lib/AuditLog.php';
require_once __DIR__ . '/../../lib/Series.php';
require_once __DIR__ . '/_functions.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::jsonError('Methode nicht erlaubt.', 405);
}

Csrf::validatePostRequest();
$currentUser = Auth::requireRole(['editor']);

$payload = series_load_payload();

$categoryId = isset($payload['category_id']) ? (int) $payload['category_id'] : 0;
$title = trim($payload['title'] ?? '');
$startInput = trim($payload['start_at'] ?? '');
$endInput = isset($payload['end_at']) ? trim((string) $payload['end_at']) : '';
$allDay = isset($payload['all_day']) ? (int) ((int) $payload['all_day'] ? 1 : 0) : 0;
$description = isset($payload['description']) ? trim((string) $payload['description']) : null;
$locationText = isset($payload['location_text']) ? trim((string) $payload['location_text']) : null;
$locationUrlInput = isset($payload['location_url']) ? trim((string) $payload['location_url']) : '';
$rrule = trim($payload['rrule'] ?? '');
$seriesTimezone = trim($payload['series_timezone'] ?? ($config['timezone'] ?? 'Europe/Berlin'));
$skipIfHoliday = isset($payload['skip_if_holiday']) ? (int) ((int) $payload['skip_if_holiday'] ? 1 : 0) : 1;

if ($categoryId <= 0) {
    Response::jsonError('Kategorie ist erforderlich.', 422);
}

if ($title === '') {
    Response::jsonError('Titel ist erforderlich.', 422);
}

if ($rrule === '') {
    Response::jsonError('RRULE ist erforderlich.', 422);
}

try {
    new DateTimeZone($seriesTimezone);
} catch (Exception) {
    Response::jsonError('Zeitzone ist ungültig.', 422);
}

try {
    Series::parseRrule($rrule, $seriesTimezone);
} catch (SeriesException $e) {
    Response::jsonError($e->getMessage(), 422);
}

if ($startInput === '') {
    Response::jsonError('Startzeit ist erforderlich.', 422);
}

$startAt = Util::parseDateTime($startInput, $seriesTimezone);
if (!$startAt) {
    Response::jsonError('Startzeit ist ungültig.', 422);
}

if ($endInput === '') {
    $endAt = $startAt;
} else {
    $endAt = Util::parseDateTime($endInput, $seriesTimezone);
    if (!$endAt) {
        Response::jsonError('Endzeit ist ungültig.', 422);
    }
}

if ($startAt > $endAt) {
    Response::jsonError('Startzeit darf nicht nach der Endzeit liegen.', 422);
}

$locationUrl = $locationUrlInput === '' ? null : $locationUrlInput;
if ($locationUrl !== null && !Util::validateUrl($locationUrl)) {
    Response::jsonError('Ort-URL ist ungültig.', 422);
}

if ($description === '') {
    $description = null;
}

if ($locationText === '') {
    $locationText = null;
}

try {
    $pdo = Db::getConnection($config);
    $pdo->beginTransaction();

    $categoryCheck = $pdo->prepare('SELECT id FROM categories WHERE id = :id LIMIT 1');
    $categoryCheck->execute(['id' => $categoryId]);
    if (!$categoryCheck->fetch(PDO::FETCH_ASSOC)) {
        $pdo->rollBack();
        Response::jsonError('Kategorie nicht gefunden.', 404);
    }

    $insertEvent = $pdo->prepare(
        'INSERT INTO events (category_id, title, description, location_text, location_url, start_at, end_at, all_day, visibility, source, created_by, updated_by, created_at)
         VALUES (:category_id, :title, :description, :location_text, :location_url, :start_at, :end_at, :all_day, "internal", "manual", :created_by, :updated_by, NOW())'
    );

    $insertEvent->execute([
        'category_id' => $categoryId,
        'title' => $title,
        'description' => $description,
        'location_text' => $locationText,
        'location_url' => $locationUrl,
        'start_at' => $startAt->format('Y-m-d H:i:s'),
        'end_at' => $endAt->format('Y-m-d H:i:s'),
        'all_day' => $allDay,
        'created_by' => $currentUser['id'],
        'updated_by' => $currentUser['id'],
    ]);

    $templateEventId = (int) $pdo->lastInsertId();

    $insertSeries = $pdo->prepare(
        'INSERT INTO event_series (template_event_id, rrule, series_timezone, skip_if_holiday, is_active, created_by, updated_by, created_at)
         VALUES (:template_event_id, :rrule, :series_timezone, :skip_if_holiday, 1, :created_by, :updated_by, NOW())'
    );

    $insertSeries->execute([
        'template_event_id' => $templateEventId,
        'rrule' => $rrule,
        'series_timezone' => $seriesTimezone,
        'skip_if_holiday' => $skipIfHoliday,
        'created_by' => $currentUser['id'],
        'updated_by' => $currentUser['id'],
    ]);

    $seriesId = (int) $pdo->lastInsertId();

    $selectEvent = $pdo->prepare(
        'SELECT e.id, e.category_id, e.title, e.description, e.location_text, e.location_url, e.start_at, e.end_at, e.all_day, e.visibility, e.source, e.external_id, e.created_by, e.updated_by, e.is_deleted, e.created_at, e.updated_at, c.color
         FROM events e
         INNER JOIN categories c ON c.id = e.category_id
         WHERE e.id = :id
         LIMIT 1'
    );
    $selectEvent->execute(['id' => $templateEventId]);
    $eventRow = $selectEvent->fetch(PDO::FETCH_ASSOC);

    $selectSeries = $pdo->prepare('SELECT * FROM event_series WHERE id = :id LIMIT 1');
    $selectSeries->execute(['id' => $seriesId]);
    $seriesRow = $selectSeries->fetch(PDO::FETCH_ASSOC);

    $afterEvent = $eventRow ? events_audit_payload($eventRow) : null;
    $afterSeries = $seriesRow ? series_audit_payload($seriesRow) : null;

    AuditLog::record($pdo, 'event', (string) $templateEventId, 'create', $currentUser['id'], null, $afterEvent);
    AuditLog::record($pdo, 'series', (string) $seriesId, 'create', $currentUser['id'], null, $afterSeries);

    $pdo->commit();

    $responseSeries = $seriesRow && $eventRow ? series_format($seriesRow, $eventRow) : ['id' => $seriesId];
    Response::jsonSuccess(['series' => $responseSeries], 201);
} catch (Exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Response::jsonError('Serie konnte nicht erstellt werden.', 500);
}
