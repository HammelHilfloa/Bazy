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
$id = isset($payload['id']) ? (int) $payload['id'] : 0;

if ($id <= 0) {
    Response::jsonError('Ungültige ID.', 422);
}

try {
    $pdo = Db::getConnection($config);

    $select = $pdo->prepare(
        'SELECT es.*, e.id AS event_id, e.category_id, e.title, e.description, e.location_text, e.location_url, e.start_at, e.end_at, e.all_day, e.visibility, e.source, e.external_id, e.created_by AS event_created_by, e.updated_by AS event_updated_by, e.is_deleted, e.created_at AS event_created_at, e.updated_at AS event_updated_at, c.color
         FROM event_series es
         INNER JOIN events e ON e.id = es.template_event_id
         INNER JOIN categories c ON c.id = e.category_id
         WHERE es.id = :id AND es.is_active = 1 AND e.is_deleted = 0
         LIMIT 1'
    );
    $select->execute(['id' => $id]);
    $existing = $select->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        Response::jsonError('Serie nicht gefunden.', 404);
    }

    $existingEvent = [
        'id' => $existing['event_id'],
        'category_id' => $existing['category_id'],
        'title' => $existing['title'],
        'description' => $existing['description'],
        'location_text' => $existing['location_text'],
        'location_url' => $existing['location_url'],
        'start_at' => $existing['start_at'],
        'end_at' => $existing['end_at'],
        'all_day' => $existing['all_day'],
        'visibility' => $existing['visibility'],
        'source' => $existing['source'],
        'external_id' => $existing['external_id'],
        'created_by' => $existing['event_created_by'],
        'updated_by' => $existing['event_updated_by'],
        'is_deleted' => $existing['is_deleted'],
        'created_at' => $existing['event_created_at'],
        'updated_at' => $existing['event_updated_at'],
        'color' => $existing['color'],
    ];

    $beforeEvent = events_audit_payload($existingEvent);
    $beforeSeries = series_audit_payload($existing);

    $seriesTimezoneInput = array_key_exists('series_timezone', $payload)
        ? trim((string) $payload['series_timezone'])
        : ($existing['series_timezone'] ?? ($config['timezone'] ?? 'Europe/Berlin'));

    try {
        new DateTimeZone($seriesTimezoneInput);
    } catch (Exception) {
        Response::jsonError('Zeitzone ist ungültig.', 422);
    }

    $rrule = array_key_exists('rrule', $payload) ? trim((string) $payload['rrule']) : (string) $existing['rrule'];
    if ($rrule === '') {
        Response::jsonError('RRULE ist erforderlich.', 422);
    }

    try {
        Series::parseRrule($rrule, $seriesTimezoneInput);
    } catch (SeriesException $e) {
        Response::jsonError($e->getMessage(), 422);
    }

    $categoryId = isset($payload['category_id']) ? (int) $payload['category_id'] : (int) $existing['category_id'];
    $title = trim($payload['title'] ?? (string) $existing['title']);
    if ($categoryId <= 0) {
        Response::jsonError('Kategorie ist erforderlich.', 422);
    }
    if ($title === '') {
        Response::jsonError('Titel ist erforderlich.', 422);
    }

    $existingStart = Util::parseDateTime($existing['start_at'], $seriesTimezoneInput);
    $existingEnd = $existing['end_at'] ? Util::parseDateTime($existing['end_at'], $seriesTimezoneInput) : $existingStart;
    if (!$existingStart || !$existingEnd) {
        Response::jsonError('Vorhandene Startzeit ist ungültig.', 500);
    }

    $startInput = array_key_exists('start_at', $payload) ? trim((string) $payload['start_at']) : '';
    $endProvided = array_key_exists('end_at', $payload);
    $endInput = $endProvided ? trim((string) $payload['end_at']) : '';

    $startAt = $startInput !== '' ? Util::parseDateTime($startInput, $seriesTimezoneInput) : $existingStart;
    if (!$startAt) {
        Response::jsonError('Startzeit ist ungültig.', 422);
    }

    if ($endProvided) {
        if ($endInput === '') {
            $endAt = $startAt;
        } else {
            $endAt = Util::parseDateTime($endInput, $seriesTimezoneInput);
            if (!$endAt) {
                Response::jsonError('Endzeit ist ungültig.', 422);
            }
        }
    } else {
        $endAt = $existingEnd ?: $startAt;
    }

    if ($startAt > $endAt) {
        Response::jsonError('Startzeit darf nicht nach der Endzeit liegen.', 422);
    }

    $allDay = isset($payload['all_day'])
        ? (int) ((int) $payload['all_day'] ? 1 : 0)
        : (int) $existing['all_day'];

    $description = array_key_exists('description', $payload)
        ? trim((string) $payload['description'])
        : ($existing['description'] ?? null);
    if ($description === '') {
        $description = null;
    }

    $locationText = array_key_exists('location_text', $payload)
        ? trim((string) $payload['location_text'])
        : ($existing['location_text'] ?? null);
    if ($locationText === '') {
        $locationText = null;
    }

    $locationUrlInput = array_key_exists('location_url', $payload)
        ? trim((string) $payload['location_url'])
        : ($existing['location_url'] ?? '');
    $locationUrl = $locationUrlInput === '' ? null : $locationUrlInput;
    if ($locationUrl !== null && !Util::validateUrl($locationUrl)) {
        Response::jsonError('Ort-URL ist ungültig.', 422);
    }

    $skipIfHoliday = isset($payload['skip_if_holiday'])
        ? (int) ((int) $payload['skip_if_holiday'] ? 1 : 0)
        : (int) $existing['skip_if_holiday'];

    $categoryCheck = $pdo->prepare('SELECT id FROM categories WHERE id = :id LIMIT 1');
    $categoryCheck->execute(['id' => $categoryId]);
    if (!$categoryCheck->fetch(PDO::FETCH_ASSOC)) {
        Response::jsonError('Kategorie nicht gefunden.', 404);
    }

    $pdo->beginTransaction();

    $updateEvent = $pdo->prepare(
        'UPDATE events
         SET category_id = :category_id,
             title = :title,
             description = :description,
             location_text = :location_text,
             location_url = :location_url,
             start_at = :start_at,
             end_at = :end_at,
             all_day = :all_day,
             updated_by = :updated_by
         WHERE id = :id'
    );

    $updateEvent->execute([
        'id' => $existing['event_id'],
        'category_id' => $categoryId,
        'title' => $title,
        'description' => $description,
        'location_text' => $locationText,
        'location_url' => $locationUrl,
        'start_at' => $startAt->format('Y-m-d H:i:s'),
        'end_at' => $endAt->format('Y-m-d H:i:s'),
        'all_day' => $allDay,
        'updated_by' => $currentUser['id'],
    ]);

    $updateSeries = $pdo->prepare(
        'UPDATE event_series
         SET rrule = :rrule,
             series_timezone = :series_timezone,
             skip_if_holiday = :skip_if_holiday,
             updated_by = :updated_by
         WHERE id = :id'
    );
    $updateSeries->execute([
        'id' => $id,
        'rrule' => $rrule,
        'series_timezone' => $seriesTimezoneInput,
        'skip_if_holiday' => $skipIfHoliday,
        'updated_by' => $currentUser['id'],
    ]);

    $select->execute(['id' => $id]);
    $updated = $select->fetch(PDO::FETCH_ASSOC);

    $updatedEventRow = $updated ? [
        'id' => $updated['event_id'],
        'category_id' => $updated['category_id'],
        'title' => $updated['title'],
        'description' => $updated['description'],
        'location_text' => $updated['location_text'],
        'location_url' => $updated['location_url'],
        'start_at' => $updated['start_at'],
        'end_at' => $updated['end_at'],
        'all_day' => $updated['all_day'],
        'visibility' => $updated['visibility'],
        'source' => $updated['source'],
        'external_id' => $updated['external_id'],
        'created_by' => $updated['event_created_by'],
        'updated_by' => $updated['event_updated_by'],
        'is_deleted' => $updated['is_deleted'],
        'created_at' => $updated['event_created_at'],
        'updated_at' => $updated['event_updated_at'],
        'color' => $updated['color'],
    ] : null;

    $afterEvent = $updatedEventRow ? events_audit_payload($updatedEventRow) : null;
    $afterSeries = $updated ? series_audit_payload($updated) : null;

    AuditLog::record($pdo, 'event', (string) $existing['event_id'], 'update', $currentUser['id'], $beforeEvent, $afterEvent);
    AuditLog::record($pdo, 'series', (string) $id, 'update', $currentUser['id'], $beforeSeries, $afterSeries);

    $pdo->commit();

    $responseSeries = ($updated && $updatedEventRow) ? series_format($updated, $updatedEventRow) : ['id' => $id];
    Response::jsonSuccess(['series' => $responseSeries]);
} catch (Exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Response::jsonError('Serie konnte nicht aktualisiert werden.', 500);
}
