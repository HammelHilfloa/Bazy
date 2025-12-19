<?php
$config = require __DIR__ . '/../../lib/bootstrap.php';

require_once __DIR__ . '/../../lib/Db.php';
require_once __DIR__ . '/../../lib/Response.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Util.php';
require_once __DIR__ . '/../../lib/Series.php';
require_once __DIR__ . '/_functions.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    Response::jsonError('Methode nicht erlaubt.', 405);
}

Auth::requireLogin();

$fromInput = trim($_GET['from'] ?? '');
$toInput = trim($_GET['to'] ?? '');

if ($fromInput === '' || $toInput === '') {
    Response::jsonError('Parameter "from" und "to" sind erforderlich.', 422);
}

$fromDate = Util::parseDateTime($fromInput . ' 00:00:00');
$toDate = Util::parseDateTime($toInput . ' 23:59:59');

if (!$fromDate || !$toDate) {
    Response::jsonError('Datumsangaben sind ungültig.', 422);
}

if ($fromDate > $toDate) {
    Response::jsonError('"from" darf nicht nach "to" liegen.', 422);
}

/**
 * @param array<string,mixed> $row
 */
function events_match_search(array $row, string $needle): bool
{
    $needle = mb_strtolower($needle);
    foreach (['title', 'description', 'location_text'] as $field) {
        if (!empty($row[$field]) && mb_stripos((string) $row[$field], $needle) !== false) {
            return true;
        }
    }

    return false;
}

$categoryIdsRaw = trim($_GET['category_ids'] ?? '');
$categoryIds = [];
if ($categoryIdsRaw !== '') {
    $categoryIds = array_values(array_unique(array_filter(array_map(static function (string $id): int {
        return (int) $id;
    }, explode(',', $categoryIdsRaw)), static function (int $id): bool {
        return $id > 0;
    })));
}

$search = trim($_GET['q'] ?? '');
$includeSystem = isset($_GET['include_system']) ? (int) $_GET['include_system'] : 0;

$params = [
    'from' => $fromDate->format('Y-m-d H:i:s'),
    'to' => $toDate->format('Y-m-d H:i:s'),
];

$filters = [
    'e.is_deleted = 0',
    'e.start_at <= :to',
    'COALESCE(e.end_at, e.start_at) >= :from',
    'NOT EXISTS (SELECT 1 FROM event_series s WHERE s.template_event_id = e.id)',
    'NOT EXISTS (SELECT 1 FROM event_overrides o WHERE o.override_event_id = e.id)',
];

if (empty($includeSystem)) {
    $filters[] = 'e.source = :source';
    $params['source'] = 'manual';
}

if (!empty($categoryIds)) {
    $placeholders = [];
    foreach ($categoryIds as $idx => $id) {
        $key = 'cat' . $idx;
        $placeholders[] = ':' . $key;
        $params[$key] = $id;
    }
    $filters[] = 'e.category_id IN (' . implode(',', $placeholders) . ')';
}

if ($search !== '') {
    $filters[] = '(e.title LIKE :q OR e.description LIKE :q OR e.location_text LIKE :q)';
    $params['q'] = '%' . $search . '%';
}

$sql = 'SELECT e.id, e.category_id, e.title, e.description, e.location_text, e.location_url, e.start_at, e.end_at, e.all_day, e.visibility, e.source, e.external_id, e.created_by, e.updated_by, e.is_deleted, e.created_at, e.updated_at, c.color
        FROM events e
        INNER JOIN categories c ON c.id = e.category_id
        WHERE ' . implode(' AND ', $filters) . '
        ORDER BY e.start_at ASC, e.id ASC';

try {
    $pdo = Db::getConnection($config);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = array_map(static fn (array $row): array => events_format_row($row), $rows);

    // Serien laden
    $seriesStmt = $pdo->prepare(
        'SELECT es.*, e.id AS event_id, e.category_id, e.title, e.description, e.location_text, e.location_url, e.start_at, e.end_at, e.all_day, e.visibility, e.source, e.external_id, e.created_by AS event_created_by, e.updated_by AS event_updated_by, e.is_deleted, e.created_at AS event_created_at, e.updated_at AS event_updated_at, c.color
         FROM event_series es
         INNER JOIN events e ON e.id = es.template_event_id
         INNER JOIN categories c ON c.id = e.category_id
         WHERE es.is_active = 1
           AND e.is_deleted = 0
           AND e.start_at <= :to
           AND COALESCE(e.end_at, e.start_at) >= :from'
    );
    $seriesStmt->execute(['from' => $params['from'], 'to' => $params['to']]);
    $seriesRows = $seriesStmt->fetchAll(PDO::FETCH_ASSOC);

    $seriesIds = array_map(static fn (array $row): int => (int) $row['id'], $seriesRows);
    $overridesBySeries = [];

    if (!empty($seriesIds)) {
        $overridePlaceholders = [];
        $overrideParams = [
            'from' => $params['from'],
            'to' => $params['to'],
        ];
        foreach ($seriesIds as $idx => $sid) {
            $key = 'sid' . $idx;
            $overridePlaceholders[] = ':' . $key;
            $overrideParams[$key] = $sid;
        }

        $overrideSql = 'SELECT o.id, o.series_id, o.occurrence_start, o.override_type, o.override_event_id,
                               oe.id AS evt_id, oe.category_id AS evt_category_id, oe.title AS evt_title, oe.description AS evt_description,
                               oe.location_text AS evt_location_text, oe.location_url AS evt_location_url, oe.start_at AS evt_start_at,
                               oe.end_at AS evt_end_at, oe.all_day AS evt_all_day, oe.visibility AS evt_visibility, oe.source AS evt_source,
                               oe.external_id AS evt_external_id, oe.created_by AS evt_created_by, oe.updated_by AS evt_updated_by,
                               oe.is_deleted AS evt_is_deleted, oe.created_at AS evt_created_at, oe.updated_at AS evt_updated_at,
                               c.color AS evt_color
                        FROM event_overrides o
                        LEFT JOIN events oe ON oe.id = o.override_event_id AND oe.is_deleted = 0
                        LEFT JOIN categories c ON c.id = oe.category_id
                        WHERE o.series_id IN (' . implode(',', $overridePlaceholders) . ')
                          AND o.occurrence_start <= :to
                          AND o.occurrence_start >= :from';

        $overrideStmt = $pdo->prepare($overrideSql);
        $overrideStmt->execute($overrideParams);
        while ($row = $overrideStmt->fetch(PDO::FETCH_ASSOC)) {
            $eventRow = null;
            if (!empty($row['evt_id'])) {
                $eventRow = [
                    'id' => $row['evt_id'],
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
                    'created_by' => $row['evt_created_by'],
                    'updated_by' => $row['evt_updated_by'],
                    'is_deleted' => $row['evt_is_deleted'],
                    'created_at' => $row['evt_created_at'],
                    'updated_at' => $row['evt_updated_at'],
                    'color' => $row['evt_color'],
                ];
            }

            $overridesBySeries[(int) $row['series_id']][$row['occurrence_start']] = [
                'override_type' => $row['override_type'],
                'event' => $eventRow,
            ];
        }
    }

    $holidayIndex = Series::buildHolidayIndex($pdo, $params['from'], $params['to']);
    $occurrenceEvents = [];

    foreach ($seriesRows as $seriesRow) {
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

        $seriesOverrides = $overridesBySeries[(int) $seriesRow['id']] ?? [];

        try {
            $occList = Series::generateOccurrences($seriesData, $fromDate, $toDate, $seriesOverrides, $holidayIndex);
        } catch (SeriesException) {
            continue;
        }

        foreach ($occList as $occ) {
            $catId = (int) $occ['category_id'];
            if (!empty($categoryIds) && !in_array($catId, $categoryIds, true)) {
                continue;
            }

            if ($search !== '' && !events_match_search($occ, $search)) {
                continue;
            }

            $formatted = events_format_row($occ);
            $formatted['series_id'] = (int) $seriesRow['id'];
            $formatted['occurrence_start'] = $occ['occurrence_start'];
            $formatted['series_rrule'] = $seriesRow['rrule'];
            $formatted['series_timezone'] = $seriesRow['series_timezone'];
            $formatted['skip_if_holiday'] = (int) $seriesRow['skip_if_holiday'];
            if (isset($occ['override_type'])) {
                $formatted['override_type'] = $occ['override_type'];
            }
            $formatted['is_series'] = 1;
            $occurrenceEvents[] = $formatted;
        }
    }

    $allEvents = array_merge($events, $occurrenceEvents);
    usort($allEvents, static function (array $a, array $b): int {
        $cmp = strcmp($a['start_at'], $b['start_at']);
        if ($cmp !== 0) {
            return $cmp;
        }
        return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
    });

    Response::jsonSuccess(['events' => $allEvents]);
} catch (Exception) {
    Response::jsonError('Events konnten nicht geladen werden.', 500);
}
