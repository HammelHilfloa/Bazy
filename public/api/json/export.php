<?php
$config = require __DIR__ . '/../../lib/bootstrap.php';

require_once __DIR__ . '/../../lib/Db.php';
require_once __DIR__ . '/../../lib/Response.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Util.php';
require_once __DIR__ . '/../../lib/Series.php';
require_once __DIR__ . '/../series/_functions.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    Response::jsonError('Methode nicht erlaubt.', 405);
}

$currentUser = Auth::requireRole(['editor']);

$fromInput = trim($_GET['from'] ?? '');
$toInput = trim($_GET['to'] ?? '');
$categoryInput = trim($_GET['category_ids'] ?? '');
$includeSystem = isset($_GET['include_system']) ? (int) $_GET['include_system'] === 1 : false;

if ($fromInput === '' || $toInput === '') {
    Response::jsonError('Parameter "from" und "to" sind erforderlich.', 422);
}

$from = Util::parseDateTime($fromInput);
$to = Util::parseDateTime($toInput);
if (!$from || !$to) {
    Response::jsonError('Zeitraum konnte nicht geparst werden.', 422);
}

if ($from > $to) {
    Response::jsonError('"from" darf nicht nach "to" liegen.', 422);
}

$categoryIds = [];
if ($categoryInput !== '') {
    foreach (explode(',', $categoryInput) as $cid) {
        $cid = (int) trim($cid);
        if ($cid > 0) {
            $categoryIds[] = $cid;
        }
    }
}

try {
    $pdo = Db::getConnection($config);

    $categorySql = 'SELECT id, name, color, sort_order, is_active, created_at
                    FROM categories';
    $categoryParams = [];
    if (!empty($categoryIds)) {
        $placeholders = [];
        foreach ($categoryIds as $idx => $cid) {
            $key = 'c' . $idx;
            $placeholders[] = ':' . $key;
            $categoryParams[$key] = $cid;
        }
        $categorySql .= ' WHERE id IN (' . implode(',', $placeholders) . ')';
    }
    $categorySql .= ' ORDER BY sort_order ASC, name ASC';

    $categoryStmt = $pdo->prepare($categorySql);
    $categoryStmt->execute($categoryParams);
    $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

    $standaloneFilters = [
        'e.is_deleted = 0',
        'e.start_at <= :to',
        'COALESCE(e.end_at, e.start_at) >= :from',
        'NOT EXISTS (SELECT 1 FROM event_series s WHERE s.template_event_id = e.id)',
        'NOT EXISTS (SELECT 1 FROM event_overrides o WHERE o.override_event_id = e.id)',
    ];
    $standaloneParams = [
        'from' => $from->format('Y-m-d H:i:s'),
        'to' => $to->format('Y-m-d H:i:s'),
    ];

    if (!$includeSystem) {
        $standaloneFilters[] = 'e.source = "manual"';
    }

    if (!empty($categoryIds)) {
        $placeholders = [];
        foreach ($categoryIds as $idx => $cid) {
            $key = 'cat' . $idx;
            $placeholders[] = ':' . $key;
            $standaloneParams[$key] = $cid;
        }
        $standaloneFilters[] = 'e.category_id IN (' . implode(',', $placeholders) . ')';
    }

    $standaloneSql = 'SELECT e.*, c.name AS category_name, c.color
                      FROM events e
                      INNER JOIN categories c ON c.id = e.category_id
                      WHERE ' . implode(' AND ', $standaloneFilters) . '
                      ORDER BY e.start_at ASC, e.id ASC';
    $standaloneStmt = $pdo->prepare($standaloneSql);
    $standaloneStmt->execute($standaloneParams);
    $standaloneEvents = $standaloneStmt->fetchAll(PDO::FETCH_ASSOC);

    $eventMap = [];
    foreach ($standaloneEvents as $row) {
        $eventMap[(int) $row['id']] = $row;
    }

    $seriesFilters = [
        'e.is_deleted = 0',
    ];
    $seriesParams = [];

    if (!$includeSystem) {
        $seriesFilters[] = 'e.source = "manual"';
    }

    if (!empty($categoryIds)) {
        $placeholders = [];
        foreach ($categoryIds as $idx => $cid) {
            $key = 'scat' . $idx;
            $placeholders[] = ':' . $key;
            $seriesParams[$key] = $cid;
        }
        $seriesFilters[] = 'e.category_id IN (' . implode(',', $placeholders) . ')';
    }

    $seriesSql = 'SELECT es.*, e.category_id, e.title, e.description, e.location_text, e.location_url, e.start_at, e.end_at, e.all_day, e.visibility, e.source, e.external_id, c.name AS category_name, c.color
                  FROM event_series es
                  INNER JOIN events e ON e.id = es.template_event_id
                  INNER JOIN categories c ON c.id = e.category_id
                  WHERE ' . implode(' AND ', $seriesFilters);

    $seriesStmt = $pdo->prepare($seriesSql);
    $seriesStmt->execute($seriesParams);
    $seriesRows = $seriesStmt->fetchAll(PDO::FETCH_ASSOC);

    $seriesIds = array_map(static fn (array $row): int => (int) $row['id'], $seriesRows);
    $overridesBySeries = [];

    if (!empty($seriesIds)) {
        $overridePlaceholders = [];
        $overrideParams = [
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ];

        foreach ($seriesIds as $idx => $sid) {
            $key = 'sid' . $idx;
            $overridePlaceholders[] = ':' . $key;
            $overrideParams[$key] = $sid;
        }

        $overrideSql = 'SELECT o.*, oe.category_id AS evt_category_id, oe.title AS evt_title, oe.description AS evt_description, oe.location_text AS evt_location_text, oe.location_url AS evt_location_url, oe.start_at AS evt_start_at, oe.end_at AS evt_end_at, oe.all_day AS evt_all_day, oe.visibility AS evt_visibility, oe.source AS evt_source, oe.external_id AS evt_external_id, c.name AS evt_category_name, c.color AS evt_color
                        FROM event_overrides o
                        LEFT JOIN events oe ON oe.id = o.override_event_id
                        LEFT JOIN categories c ON c.id = oe.category_id
                        WHERE o.series_id IN (' . implode(',', $overridePlaceholders) . ')
                          AND o.occurrence_start >= :from
                          AND o.occurrence_start <= :to';

        $overrideStmt = $pdo->prepare($overrideSql);
        $overrideStmt->execute($overrideParams);
        while ($row = $overrideStmt->fetch(PDO::FETCH_ASSOC)) {
            $seriesId = (int) $row['series_id'];
            $overridesBySeries[$seriesId][] = $row;

            if (!empty($row['override_event_id']) && (int) $row['override_event_id'] > 0) {
                $eventMap[(int) $row['override_event_id']] = [
                    'id' => (int) $row['override_event_id'],
                    'category_id' => $row['evt_category_id'],
                    'title' => $row['evt_title'],
                    'description' => $row['evt_description'],
                    'location_text' => $row['evt_location_text'],
                    'location_url' => $row['evt_location_url'],
                    'start_at' => $row['evt_start_at'],
                    'end_at' => $row['evt_end_at'],
                    'all_day' => $row['evt_all_day'],
                    'visibility' => $row['evt_visibility'],
                    'source' => $row['evt_source'],
                    'external_id' => $row['evt_external_id'],
                    'category_name' => $row['evt_category_name'],
                    'color' => $row['evt_color'],
                ];
            }
        }
    }

    $holidayIndex = Series::buildHolidayIndex($pdo, $from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s'));

    $seriesExport = [];
    $overridesExport = [];

    foreach ($seriesRows as $seriesRow) {
        $templateEvent = [
            'id' => (int) $seriesRow['template_event_id'],
            'category_id' => (int) $seriesRow['category_id'],
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
            'color' => $seriesRow['color'],
            'category_name' => $seriesRow['category_name'],
        ];

        $seriesData = [
            'id' => (int) $seriesRow['id'],
            'rrule' => $seriesRow['rrule'],
            'series_timezone' => $seriesRow['series_timezone'],
            'skip_if_holiday' => (int) $seriesRow['skip_if_holiday'],
            'is_active' => (int) $seriesRow['is_active'],
            'template_event' => $templateEvent,
        ];

        $seriesOverrides = [];
        if (!empty($overridesBySeries[(int) $seriesRow['id']])) {
            foreach ($overridesBySeries[(int) $seriesRow['id']] as $o) {
                $seriesOverrides[$o['occurrence_start']] = [
                    'override_type' => $o['override_type'],
                    'event' => isset($eventMap[(int) $o['override_event_id']]) ? $eventMap[(int) $o['override_event_id']] : null,
                ];
            }
        }

        try {
            $occurrences = Series::generateOccurrences($seriesData, $from, $to, $seriesOverrides, $holidayIndex);
        } catch (SeriesException) {
            $occurrences = [];
        }

        $hasRelevantOverride = !empty($overridesBySeries[(int) $seriesRow['id']]);
        if (empty($occurrences) && !$hasRelevantOverride) {
            continue;
        }

        $eventMap[(int) $seriesRow['template_event_id']] = $templateEvent;

        $seriesExport[] = [
            'id' => (int) $seriesRow['id'],
            'template_event_id' => (int) $seriesRow['template_event_id'],
            'rrule' => $seriesRow['rrule'],
            'series_timezone' => $seriesRow['series_timezone'],
            'skip_if_holiday' => (int) $seriesRow['skip_if_holiday'],
            'is_active' => (int) $seriesRow['is_active'],
        ];

        if (!empty($overridesBySeries[(int) $seriesRow['id']])) {
            foreach ($overridesBySeries[(int) $seriesRow['id']] as $o) {
                $overridesExport[] = [
                    'id' => (int) $o['id'],
                    'series_id' => (int) $o['series_id'],
                    'occurrence_start' => $o['occurrence_start'],
                    'override_type' => $o['override_type'],
                    'override_event_id' => $o['override_event_id'] ? (int) $o['override_event_id'] : null,
                ];
            }
        }
    }

    $events = array_values(array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'category_id' => (int) $row['category_id'],
            'category_name' => $row['category_name'] ?? null,
            'title' => $row['title'],
            'description' => $row['description'],
            'location_text' => $row['location_text'],
            'location_url' => $row['location_url'],
            'start_at' => $row['start_at'],
            'end_at' => $row['end_at'] ?? $row['start_at'],
            'all_day' => (int) $row['all_day'],
            'visibility' => $row['visibility'],
            'source' => $row['source'],
            'external_id' => $row['external_id'],
        ];
    }, $eventMap));

    $payload = [
        'meta' => [
            'exported_at' => date('c'),
            'version' => 1,
            'range' => [
                'from' => $from->format('Y-m-d H:i:s'),
                'to' => $to->format('Y-m-d H:i:s'),
                'category_ids' => $categoryIds,
                'include_system' => $includeSystem ? 1 : 0,
            ],
            'requested_by' => $currentUser['username'] ?? 'unknown',
        ],
        'categories' => array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'color' => $row['color'],
                'sort_order' => (int) $row['sort_order'],
                'is_active' => (int) $row['is_active'],
                'created_at' => $row['created_at'],
            ];
        }, $categories),
        'events' => $events,
        'series' => $seriesExport,
        'overrides' => $overridesExport,
    ];

    Response::send($payload, 200);
} catch (Exception) {
    Response::jsonError('Export fehlgeschlagen.', 500);
}
