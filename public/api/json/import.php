<?php
$config = require __DIR__ . '/../../lib/bootstrap.php';

require_once __DIR__ . '/../../lib/Db.php';
require_once __DIR__ . '/../../lib/Response.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Csrf.php';
require_once __DIR__ . '/../../lib/Util.php';
require_once __DIR__ . '/../../lib/AuditLog.php';
require_once __DIR__ . '/../../lib/Series.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::jsonError('Methode nicht erlaubt.', 405);
}

Csrf::validatePostRequest();
$currentUser = Auth::requireRole(['editor']);

$rawJson = '';

if (isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
    $rawJson = (string) file_get_contents($_FILES['file']['tmp_name']);
} else {
    $rawJson = (string) file_get_contents('php://input');
}

$data = json_decode($rawJson, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    Response::jsonError('Ungültiges JSON.', 422);
}

if (!isset($data['meta']['version']) || (int) $data['meta']['version'] !== 1) {
    Response::jsonError('Nicht unterstützte Version.', 422);
}

$categories = isset($data['categories']) && is_array($data['categories']) ? $data['categories'] : [];
$events = isset($data['events']) && is_array($data['events']) ? $data['events'] : [];
$seriesItems = isset($data['series']) && is_array($data['series']) ? $data['series'] : [];
$overrides = isset($data['overrides']) && is_array($data['overrides']) ? $data['overrides'] : [];

$report = [
    'created' => [],
    'updated' => [],
    'skipped' => [],
    'errors' => [],
];

try {
    $pdo = Db::getConnection($config);
    $pdo->beginTransaction();

    $categoryMap = [];

    foreach ($categories as $cat) {
        $name = trim((string) ($cat['name'] ?? ''));
        if ($name === '') {
            $report['errors'][] = ['type' => 'category', 'reason' => 'Name fehlt'];
            continue;
        }

        $color = trim((string) ($cat['color'] ?? '#999999'));
        $sortOrder = isset($cat['sort_order']) ? (int) $cat['sort_order'] : 0;
        $isActive = isset($cat['is_active']) ? (int) ((int) $cat['is_active'] ? 1 : 0) : 1;

        $existingStmt = $pdo->prepare('SELECT id, color, sort_order, is_active FROM categories WHERE name = :name LIMIT 1');
        $existingStmt->execute(['name' => $name]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $update = $pdo->prepare('UPDATE categories SET color = :color, sort_order = :sort_order, is_active = :is_active WHERE id = :id');
            $update->execute([
                'color' => $color,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
                'id' => $existing['id'],
            ]);
            $categoryId = (int) $existing['id'];
            $categoryMap[$name] = $categoryId;
            $report['updated'][] = ['type' => 'category', 'name' => $name, 'id' => $categoryId];
        } else {
            $insert = $pdo->prepare('INSERT INTO categories (name, color, sort_order, is_active, created_at) VALUES (:name, :color, :sort_order, :is_active, NOW())');
            $insert->execute([
                'name' => $name,
                'color' => $color,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
            ]);
            $categoryId = (int) $pdo->lastInsertId();
            $categoryMap[$name] = $categoryId;
            $report['created'][] = ['type' => 'category', 'name' => $name, 'id' => $categoryId];
        }
    }

    $knownCategoryIds = array_fill_keys(array_values($categoryMap), true);

    $eventIdMap = [];

    foreach ($events as $evt) {
        $title = trim((string) ($evt['title'] ?? ''));
        $startAtRaw = trim((string) ($evt['start_at'] ?? ''));
        $categoryName = trim((string) ($evt['category_name'] ?? ''));
        $categoryId = null;

        if ($categoryName !== '' && isset($categoryMap[$categoryName])) {
            $categoryId = $categoryMap[$categoryName];
        } elseif (isset($evt['category_id'])) {
            $cid = (int) $evt['category_id'];
            if ($cid > 0) {
                $categoryId = $cid;
            }
        }

        if ($categoryId && !isset($knownCategoryIds[$categoryId])) {
            $checkCat = $pdo->prepare('SELECT id FROM categories WHERE id = :id LIMIT 1');
            $checkCat->execute(['id' => $categoryId]);
            if ($checkCat->fetchColumn()) {
                $knownCategoryIds[$categoryId] = true;
            } else {
                $categoryId = null;
            }
        }

        if ($title === '' || $startAtRaw === '' || !$categoryId) {
            $report['errors'][] = ['type' => 'event', 'reason' => 'Ungültige Pflichtfelder', 'event' => $evt];
            continue;
        }

        $startAt = Util::parseDateTime($startAtRaw);
        $endAtInput = trim((string) ($evt['end_at'] ?? $startAtRaw));
        $endAt = Util::parseDateTime($endAtInput);
        if (!$startAt || !$endAt) {
            $report['errors'][] = ['type' => 'event', 'reason' => 'Zeitformat ungültig', 'event' => $evt];
            continue;
        }

        $payload = [
            'category_id' => $categoryId,
            'title' => $title,
            'description' => isset($evt['description']) ? (string) $evt['description'] : null,
            'location_text' => isset($evt['location_text']) ? (string) $evt['location_text'] : null,
            'location_url' => isset($evt['location_url']) ? (string) $evt['location_url'] : null,
            'start_at' => $startAt->format('Y-m-d H:i:s'),
            'end_at' => $endAt->format('Y-m-d H:i:s'),
            'all_day' => isset($evt['all_day']) ? (int) ((int) $evt['all_day'] ? 1 : 0) : 0,
            'visibility' => $evt['visibility'] ?? 'internal',
            'source' => $evt['source'] ?? 'manual',
            'external_id' => isset($evt['external_id']) ? (string) $evt['external_id'] : null,
        ];

        $exportedId = isset($evt['id']) ? (int) $evt['id'] : null;

        $existingId = null;
        if ($payload['external_id'] !== null && $payload['external_id'] !== '') {
            $source = $payload['source'] ?: 'manual';
            $find = $pdo->prepare('SELECT id FROM events WHERE source = :source AND external_id = :external_id LIMIT 1');
            $find->execute([
                'source' => $source,
                'external_id' => $payload['external_id'],
            ]);
            $existingRaw = $find->fetchColumn();
            $existingId = $existingRaw ? (int) $existingRaw : null;
        }

        if ($existingId) {
            $update = $pdo->prepare(
                'UPDATE events
                 SET category_id = :category_id,
                     title = :title,
                     description = :description,
                     location_text = :location_text,
                     location_url = :location_url,
                     start_at = :start_at,
                     end_at = :end_at,
                     all_day = :all_day,
                     visibility = :visibility,
                     updated_by = :updated_by,
                     source = :source,
                     external_id = :external_id
                 WHERE id = :id'
            );
            $update->execute([
                'category_id' => $payload['category_id'],
                'title' => $payload['title'],
                'description' => $payload['description'],
                'location_text' => $payload['location_text'],
                'location_url' => $payload['location_url'],
                'start_at' => $payload['start_at'],
                'end_at' => $payload['end_at'],
                'all_day' => $payload['all_day'],
                'visibility' => $payload['visibility'],
                'updated_by' => $currentUser['id'],
                'source' => $payload['source'],
                'external_id' => $payload['external_id'],
                'id' => $existingId,
            ]);
            $eventIdMap[$exportedId ?? $existingId] = $existingId;
            $report['updated'][] = ['type' => 'event', 'id' => $existingId, 'title' => $payload['title']];
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO events (category_id, title, description, location_text, location_url, start_at, end_at, all_day, visibility, source, external_id, created_by, updated_by, created_at)
                 VALUES (:category_id, :title, :description, :location_text, :location_url, :start_at, :end_at, :all_day, :visibility, :source, :external_id, :created_by, :updated_by, NOW())'
            );
            $insert->execute([
                'category_id' => $payload['category_id'],
                'title' => $payload['title'],
                'description' => $payload['description'],
                'location_text' => $payload['location_text'],
                'location_url' => $payload['location_url'],
                'start_at' => $payload['start_at'],
                'end_at' => $payload['end_at'],
                'all_day' => $payload['all_day'],
                'visibility' => $payload['visibility'],
                'source' => $payload['source'],
                'external_id' => $payload['external_id'] ?: null,
                'created_by' => $currentUser['id'],
                'updated_by' => $currentUser['id'],
            ]);
            $newId = (int) $pdo->lastInsertId();
            if ($exportedId !== null) {
                $eventIdMap[$exportedId] = $newId;
            }
            $report['created'][] = ['type' => 'event', 'id' => $newId, 'title' => $payload['title']];
        }
    }

    $seriesIdMap = [];

    foreach ($seriesItems as $ser) {
        $templateExportId = isset($ser['template_event_id']) ? (int) $ser['template_event_id'] : null;
        $templateId = $templateExportId !== null && isset($eventIdMap[$templateExportId]) ? (int) $eventIdMap[$templateExportId] : null;
        $rrule = trim((string) ($ser['rrule'] ?? ''));
        $seriesTz = trim((string) ($ser['series_timezone'] ?? ($config['timezone'] ?? 'Europe/Berlin')));

        if (!$templateId || $rrule === '') {
            $report['errors'][] = ['type' => 'series', 'reason' => 'Template oder RRULE fehlt', 'series' => $ser];
            continue;
        }

        try {
            Series::parseRrule($rrule, $seriesTz);
        } catch (SeriesException $ex) {
            $report['errors'][] = ['type' => 'series', 'reason' => $ex->getMessage(), 'series' => $ser];
            continue;
        }

        $skipIfHoliday = isset($ser['skip_if_holiday']) ? (int) ((int) $ser['skip_if_holiday'] ? 1 : 0) : 1;
        $isActive = isset($ser['is_active']) ? (int) ((int) $ser['is_active'] ? 1 : 0) : 1;

        $existingSeriesStmt = $pdo->prepare('SELECT id FROM event_series WHERE template_event_id = :template_event_id LIMIT 1');
        $existingSeriesStmt->execute(['template_event_id' => $templateId]);
        $existingSeriesId = $existingSeriesStmt->fetchColumn();

        if ($existingSeriesId) {
            $update = $pdo->prepare(
                'UPDATE event_series
                 SET rrule = :rrule,
                     series_timezone = :series_timezone,
                     skip_if_holiday = :skip_if_holiday,
                     is_active = :is_active,
                     updated_by = :updated_by
                 WHERE id = :id'
            );
            $update->execute([
                'rrule' => $rrule,
                'series_timezone' => $seriesTz,
                'skip_if_holiday' => $skipIfHoliday,
                'is_active' => $isActive,
                'updated_by' => $currentUser['id'],
                'id' => $existingSeriesId,
            ]);
            $seriesId = (int) $existingSeriesId;
            $report['updated'][] = ['type' => 'series', 'id' => $seriesId, 'template_event_id' => $templateId];
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO event_series (template_event_id, rrule, series_timezone, skip_if_holiday, is_active, created_by, updated_by, created_at)
                 VALUES (:template_event_id, :rrule, :series_timezone, :skip_if_holiday, :is_active, :created_by, :updated_by, NOW())'
            );
            $insert->execute([
                'template_event_id' => $templateId,
                'rrule' => $rrule,
                'series_timezone' => $seriesTz,
                'skip_if_holiday' => $skipIfHoliday,
                'is_active' => $isActive,
                'created_by' => $currentUser['id'],
                'updated_by' => $currentUser['id'],
            ]);
            $seriesId = (int) $pdo->lastInsertId();
            $report['created'][] = ['type' => 'series', 'id' => $seriesId, 'template_event_id' => $templateId];
        }

        $exportSeriesId = isset($ser['id']) ? (int) $ser['id'] : null;
        if ($exportSeriesId !== null) {
            $seriesIdMap[$exportSeriesId] = $seriesId;
        }
    }

    foreach ($overrides as $override) {
        $exportSeriesId = isset($override['series_id']) ? (int) $override['series_id'] : null;
        $seriesId = $exportSeriesId !== null && isset($seriesIdMap[$exportSeriesId]) ? (int) $seriesIdMap[$exportSeriesId] : null;
        $occurrenceStart = trim((string) ($override['occurrence_start'] ?? ''));
        $overrideType = trim((string) ($override['override_type'] ?? ''));

        if (!$seriesId || $occurrenceStart === '' || ($overrideType !== 'modified' && $overrideType !== 'cancelled')) {
            $report['errors'][] = ['type' => 'override', 'reason' => 'Ungültige Daten', 'override' => $override];
            continue;
        }

        $overrideStart = Util::parseDateTime($occurrenceStart);
        if (!$overrideStart) {
            $report['errors'][] = ['type' => 'override', 'reason' => 'Ungültiges Datum', 'override' => $override];
            continue;
        }

        $overrideEventId = null;
        if ($overrideType === 'modified' && isset($override['override_event_id'])) {
            $exportEventId = (int) $override['override_event_id'];
            if (isset($eventIdMap[$exportEventId])) {
                $overrideEventId = (int) $eventIdMap[$exportEventId];
            } else {
                $report['errors'][] = ['type' => 'override', 'reason' => 'Override-Event fehlt', 'override' => $override];
                continue;
            }
        }

        $existingOverrideStmt = $pdo->prepare('SELECT id FROM event_overrides WHERE series_id = :series_id AND occurrence_start = :occurrence_start LIMIT 1');
        $existingOverrideStmt->execute([
            'series_id' => $seriesId,
            'occurrence_start' => $overrideStart->format('Y-m-d H:i:s'),
        ]);
        $existingOverrideId = $existingOverrideStmt->fetchColumn();

        if ($existingOverrideId) {
            $update = $pdo->prepare(
                'UPDATE event_overrides
                 SET override_type = :override_type,
                     override_event_id = :override_event_id
                 WHERE id = :id'
            );
            $update->execute([
                'override_type' => $overrideType,
                'override_event_id' => $overrideEventId,
                'id' => $existingOverrideId,
            ]);
            $report['updated'][] = ['type' => 'override', 'id' => (int) $existingOverrideId, 'series_id' => $seriesId, 'occurrence_start' => $overrideStart->format('Y-m-d H:i:s')];
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO event_overrides (series_id, occurrence_start, override_type, override_event_id, created_by, created_at)
                 VALUES (:series_id, :occurrence_start, :override_type, :override_event_id, :created_by, NOW())'
            );
            $insert->execute([
                'series_id' => $seriesId,
                'occurrence_start' => $overrideStart->format('Y-m-d H:i:s'),
                'override_type' => $overrideType,
                'override_event_id' => $overrideEventId,
                'created_by' => $currentUser['id'],
            ]);
            $newOverrideId = (int) $pdo->lastInsertId();
            $report['created'][] = ['type' => 'override', 'id' => $newOverrideId, 'series_id' => $seriesId, 'occurrence_start' => $overrideStart->format('Y-m-d H:i:s')];
        }
    }

    AuditLog::record(
        $pdo,
        'import',
        'json',
        'import',
        $currentUser['id'],
        null,
        $report
    );

    $pdo->commit();

    Response::jsonSuccess(['report' => $report]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Response::jsonError('Import fehlgeschlagen.', 500, ['error' => $e->getMessage()]);
}
