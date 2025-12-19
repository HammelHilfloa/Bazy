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

    $existingOverrideStmt = $pdo->prepare('SELECT * FROM event_overrides WHERE series_id = :series_id AND occurrence_start = :occurrence_start LIMIT 1');
    $existingOverrideStmt->execute(['series_id' => $seriesId, 'occurrence_start' => $occurrenceKey]);
    $existingOverride = $existingOverrideStmt->fetch(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();

    $before = $existingOverride ?: null;

    if ($existingOverride) {
        $update = $pdo->prepare(
            'UPDATE event_overrides
             SET override_type = "cancelled",
                 override_event_id = NULL,
                 created_by = :created_by
             WHERE id = :id'
        );
        $update->execute([
            'id' => $existingOverride['id'],
            'created_by' => $currentUser['id'],
        ]);
        $overrideId = (int) $existingOverride['id'];
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO event_overrides (series_id, occurrence_start, override_type, override_event_id, created_by, created_at)
             VALUES (:series_id, :occurrence_start, "cancelled", NULL, :created_by, NOW())'
        );
        $insert->execute([
            'series_id' => $seriesId,
            'occurrence_start' => $occurrenceKey,
            'created_by' => $currentUser['id'],
        ]);
        $overrideId = (int) $pdo->lastInsertId();
    }

    $after = [
        'id' => $overrideId,
        'series_id' => $seriesId,
        'occurrence_start' => $occurrenceKey,
        'override_type' => 'cancelled',
        'override_event_id' => null,
        'created_by' => $currentUser['id'],
    ];

    AuditLog::record($pdo, 'series', (string) $seriesId, 'update', $currentUser['id'], $before, $after);

    $pdo->commit();

    Response::jsonSuccess([
        'override' => [
            'series_id' => $seriesId,
            'occurrence_start' => $occurrenceKey,
            'override_type' => 'cancelled',
        ],
    ]);
} catch (Exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Response::jsonError('Override konnte nicht gespeichert werden.', 500);
}
