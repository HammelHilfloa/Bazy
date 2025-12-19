<?php
$config = require __DIR__ . '/../../lib/bootstrap.php';

require_once __DIR__ . '/../../lib/Db.php';
require_once __DIR__ . '/../../lib/Response.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Csrf.php';
require_once __DIR__ . '/../../lib/Util.php';
require_once __DIR__ . '/../../lib/AuditLog.php';
require_once __DIR__ . '/../../lib/Series.php';
require_once __DIR__ . '/../events/_functions.php';
require_once __DIR__ . '/_functions.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::jsonError('Methode nicht erlaubt.', 405);
}

Csrf::validatePostRequest();
$currentUser = Auth::requireRole(['editor']);

$payload = series_load_payload();
$seriesId = isset($payload['series_id']) ? (int) $payload['series_id'] : 0;
$occurrenceInput = trim($payload['occurrence_start'] ?? '');

if ($seriesId <= 0 || $occurrenceInput === '') {
    Response::jsonError('Serie und occurrence_start sind erforderlich.', 422);
}

try {
    $pdo = Db::getConnection($config);

    $selectSeries = $pdo->prepare(
        'SELECT es.*, e.id AS event_id, e.category_id, e.title, e.description, e.location_text, e.location_url, e.start_at, e.end_at, e.all_day, e.visibility, e.source, e.external_id, e.created_by AS event_created_by, e.updated_by AS event_updated_by, e.is_deleted, e.created_at AS event_created_at, e.updated_at AS event_updated_at, c.color
         FROM event_series es
         INNER JOIN events e ON e.id = es.template_event_id
         INNER JOIN categories c ON c.id = e.category_id
         WHERE es.id = :id AND es.is_active = 1 AND e.is_deleted = 0
         LIMIT 1'
    );
    $selectSeries->execute(['id' => $seriesId]);
    $seriesRow = $selectSeries->fetch(PDO::FETCH_ASSOC);

    if (!$seriesRow) {
        Response::jsonError('Serie nicht gefunden oder inaktiv.', 404);
    }

    $seriesTimezone = $seriesRow['series_timezone'] ?? ($config['timezone'] ?? 'Europe/Berlin');
    try {
        new DateTimeZone($seriesTimezone);
    } catch (Exception) {
        Response::jsonError('Zeitzone ist ungültig.', 422);
    }

    $occurrenceStart = Util::parseDateTime($occurrenceInput, $seriesTimezone);
    if (!$occurrenceStart) {
        Response::jsonError('occurrence_start ist ungültig.', 422);
    }
    $occurrenceKey = $occurrenceStart->format('Y-m-d H:i:s');

    $overrideCheck = $pdo->prepare('SELECT id FROM event_overrides WHERE series_id = :series_id AND occurrence_start = :occurrence_start LIMIT 1');
    $overrideCheck->execute(['series_id' => $seriesId, 'occurrence_start' => $occurrenceKey]);
    if ($overrideCheck->fetch(PDO::FETCH_ASSOC)) {
        Response::jsonError('Für dieses Vorkommen existiert bereits ein Override.', 409);
    }

    $templateEvent = [
        'id' => $seriesRow['event_id'],
        'category_id' => $seriesRow['category_id'],
        'title' => $seriesRow['title'],
        'description' => $seriesRow['description'],
        'location_text' => $seriesRow['location_text'],
        'location_url' => $seriesRow['location_url'],
        'start_at' => $seriesRow['start_at'],
        'end_at' => $seriesRow['end_at'],
        'all_day' => $seriesRow['all_day'],
        'visibility' => $seriesRow['visibility'],
        'source' => $seriesRow['source'],
        'external_id' => $seriesRow['external_id'],
        'created_by' => $seriesRow['event_created_by'],
        'updated_by' => $seriesRow['event_updated_by'],
        'is_deleted' => $seriesRow['is_deleted'],
        'created_at' => $seriesRow['event_created_at'],
        'updated_at' => $seriesRow['event_updated_at'],
        'color' => $seriesRow['color'],
    ];

    $seriesData = $seriesRow;
    $seriesData['template_event'] = $templateEvent;

    $holidayIndex = Series::buildHolidayIndex(
        $pdo,
        $occurrenceStart->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
        $occurrenceStart->setTime(23, 59, 59)->format('Y-m-d H:i:s')
    );

    $occurrences = Series::generateOccurrences(
        $seriesData,
        $occurrenceStart->setTime(0, 0, 0),
        $occurrenceStart->setTime(23, 59, 59),
        [],
        $holidayIndex
    );

    $isValidOccurrence = false;
    foreach ($occurrences as $occ) {
        if (($occ['occurrence_start'] ?? null) === $occurrenceKey) {
            $isValidOccurrence = true;
            break;
        }
    }

    if (!$isValidOccurrence) {
        Response::jsonError('Das angegebene Vorkommen gehört nicht zur Serie oder wurde übersprungen.', 422);
    }

    $templateStart = Util::parseDateTime($seriesRow['start_at'], $seriesTimezone);
    $templateEnd = Util::parseDateTime($seriesRow['end_at'] ?? $seriesRow['start_at'], $seriesTimezone) ?: $templateStart;
    $defaultDuration = max(0, $templateEnd->getTimestamp() - $templateStart->getTimestamp());

    $categoryId = isset($payload['category_id']) ? (int) $payload['category_id'] : (int) $seriesRow['category_id'];
    $title = trim($payload['title'] ?? (string) $seriesRow['title']);
    if ($categoryId <= 0) {
        Response::jsonError('Kategorie ist erforderlich.', 422);
    }
    if ($title === '') {
        Response::jsonError('Titel ist erforderlich.', 422);
    }

    $startInput = array_key_exists('start_at', $payload) ? trim((string) $payload['start_at']) : '';
    $endProvided = array_key_exists('end_at', $payload);
    $endInput = $endProvided ? trim((string) $payload['end_at']) : '';

    $startAt = $startInput !== '' ? Util::parseDateTime($startInput, $seriesTimezone) : $occurrenceStart;
    if (!$startAt) {
        Response::jsonError('Startzeit ist ungültig.', 422);
    }

    if ($endProvided) {
        if ($endInput === '') {
            $endAt = $startAt;
        } else {
            $endAt = Util::parseDateTime($endInput, $seriesTimezone);
            if (!$endAt) {
                Response::jsonError('Endzeit ist ungültig.', 422);
            }
        }
    } else {
        $endAt = $startAt->modify('+' . $defaultDuration . ' seconds');
    }

    if ($startAt > $endAt) {
        Response::jsonError('Startzeit darf nicht nach der Endzeit liegen.', 422);
    }

    $allDay = isset($payload['all_day'])
        ? (int) ((int) $payload['all_day'] ? 1 : 0)
        : (int) $seriesRow['all_day'];

    $description = array_key_exists('description', $payload)
        ? trim((string) $payload['description'])
        : ($seriesRow['description'] ?? null);
    if ($description === '') {
        $description = null;
    }

    $locationText = array_key_exists('location_text', $payload)
        ? trim((string) $payload['location_text'])
        : ($seriesRow['location_text'] ?? null);
    if ($locationText === '') {
        $locationText = null;
    }

    $locationUrlInput = array_key_exists('location_url', $payload)
        ? trim((string) $payload['location_url'])
        : ($seriesRow['location_url'] ?? '');
    $locationUrl = $locationUrlInput === '' ? null : $locationUrlInput;
    if ($locationUrl !== null && !Util::validateUrl($locationUrl)) {
        Response::jsonError('Ort-URL ist ungültig.', 422);
    }

    $categoryCheck = $pdo->prepare('SELECT id FROM categories WHERE id = :id LIMIT 1');
    $categoryCheck->execute(['id' => $categoryId]);
    if (!$categoryCheck->fetch(PDO::FETCH_ASSOC)) {
        Response::jsonError('Kategorie nicht gefunden.', 404);
    }

    $pdo->beginTransaction();

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

    $overrideEventId = (int) $pdo->lastInsertId();

    $insertOverride = $pdo->prepare(
        'INSERT INTO event_overrides (series_id, occurrence_start, override_type, override_event_id, created_by, created_at)
         VALUES (:series_id, :occurrence_start, "modified", :override_event_id, :created_by, NOW())'
    );
    $insertOverride->execute([
        'series_id' => $seriesId,
        'occurrence_start' => $occurrenceKey,
        'override_event_id' => $overrideEventId,
        'created_by' => $currentUser['id'],
    ]);

    $selectEvent = $pdo->prepare(
        'SELECT e.id, e.category_id, e.title, e.description, e.location_text, e.location_url, e.start_at, e.end_at, e.all_day, e.visibility, e.source, e.external_id, e.created_by, e.updated_by, e.is_deleted, e.created_at, e.updated_at, c.color
         FROM events e
         INNER JOIN categories c ON c.id = e.category_id
         WHERE e.id = :id
         LIMIT 1'
    );
    $selectEvent->execute(['id' => $overrideEventId]);
    $eventRow = $selectEvent->fetch(PDO::FETCH_ASSOC);

    $overrideAudit = [
        'series_id' => $seriesId,
        'occurrence_start' => $occurrenceKey,
        'override_type' => 'modified',
        'override_event_id' => $overrideEventId,
    ];

    AuditLog::record($pdo, 'event', (string) $overrideEventId, 'create', $currentUser['id'], null, $eventRow ? events_audit_payload($eventRow) : null);
    AuditLog::record($pdo, 'series', (string) $seriesId, 'update', $currentUser['id'], null, $overrideAudit);

    $pdo->commit();

    if ($eventRow) {
        $responseEvent = events_format_row($eventRow);
        $responseEvent['series_id'] = $seriesId;
        $responseEvent['occurrence_start'] = $occurrenceKey;
        $responseEvent['override_type'] = 'modified';
        Response::jsonSuccess(['override' => $responseEvent], 201);
    }

    Response::jsonSuccess(['override' => ['series_id' => $seriesId, 'occurrence_start' => $occurrenceKey, 'override_type' => 'modified']], 201);
} catch (Exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Response::jsonError('Override konnte nicht gespeichert werden.', 500);
}
