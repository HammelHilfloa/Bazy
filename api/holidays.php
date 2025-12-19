<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
if ($year < 2000 || $year > 2100) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültiges Jahr']);
    exit;
}

$region = 'DE-NW';
$syncKey = 'last_sync_de-nw_' . $year;

try {
    $pdo = get_pdo();
} catch (Throwable $e) {
    http_response_code(500);
    error_log('DB connection failed: ' . $e->getMessage());
    echo json_encode(['error' => 'DB-Verbindung fehlgeschlagen']);
    exit;
}

try {
    $shouldRefresh = false;

    $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM holiday_entries WHERE region = ? AND year = ?');
    $stmt->execute([$region, $year]);
    $hasData = (int) $stmt->fetchColumn() > 0;

    $lastSyncRaw = get_setting($pdo, $syncKey);
    if (!$hasData) {
        $shouldRefresh = true;
    } elseif ($lastSyncRaw) {
        $lastSync = new DateTimeImmutable($lastSyncRaw);
        if ($lastSync < (new DateTimeImmutable('-7 days'))) {
            $shouldRefresh = true;
        }
    }

    if ($shouldRefresh) {
        $entries = refreshHolidays($pdo, $year, $region);
    } else {
        $entries = fetchFromDb($pdo, $year, $region);
    }

    echo json_encode($entries);
} catch (Throwable $e) {
    error_log('Holiday fetch error: ' . $e->getMessage());
    try {
        $fallback = fetchFromDb($pdo, $year, $region);
    } catch (Throwable $inner) {
        error_log('Holiday fallback fetch failed: ' . $inner->getMessage());
        $fallback = [];
    }
    // Best effort: return cached/empty data instead of surfacing an error to the UI
    http_response_code(200);
    echo json_encode($fallback);
}

function refreshHolidays(PDO $pdo, int $year, string $region): array
{
    $sources = [];
    try {
        $entries = fetchOpenHolidays($year, $region);
    } catch (Throwable $e) {
        error_log('OpenHolidays fallback: ' . $e->getMessage());
        $entries = [];
    }

    if (empty($entries)) {
        $entries = array_merge($entries, fetchFerienApi($year, $region));
        $entries = array_merge($entries, fetchNager($year, $region));
    }

    if (empty($entries)) {
        throw new RuntimeException('Keine Daten von externen APIs erhalten.');
    }

    foreach ($entries as $e) {
        $sources[$e['source']] = true;
    }

    $pdo->beginTransaction();
    try {
        if ($sources) {
            $placeholders = implode(',', array_fill(0, count($sources), '?'));
            $params = array_merge([$region, $year], array_keys($sources));
            $stmt = $pdo->prepare("DELETE FROM holiday_entries WHERE region = ? AND year = ? AND source IN ({$placeholders})");
            $stmt->execute($params);
        }

        $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $insertSql = $isSqlite
            ? 'INSERT INTO holiday_entries (source, kind, name, start_date, end_date, region, year, checksum, fetched_at, created_at, updated_at)
                VALUES (:source, :kind, :name, :start_date, :end_date, :region, :year, :checksum, :fetched_at, :created_at, :updated_at)
                ON CONFLICT(region, year, kind, name, start_date, end_date) DO UPDATE SET
                    source = excluded.source,
                    kind = excluded.kind,
                    name = excluded.name,
                    start_date = excluded.start_date,
                    end_date = excluded.end_date,
                    checksum = excluded.checksum,
                    fetched_at = excluded.fetched_at,
                    updated_at = excluded.updated_at'
            : 'INSERT INTO holiday_entries (source, kind, name, start_date, end_date, region, year, checksum, fetched_at, created_at, updated_at)
                VALUES (:source, :kind, :name, :start_date, :end_date, :region, :year, :checksum, :fetched_at, :created_at, :updated_at)
                ON DUPLICATE KEY UPDATE
                    source = VALUES(source),
                    kind = VALUES(kind),
                    name = VALUES(name),
                    start_date = VALUES(start_date),
                    end_date = VALUES(end_date),
                    checksum = VALUES(checksum),
                    fetched_at = VALUES(fetched_at),
                    updated_at = VALUES(updated_at)';
        $insert = $pdo->prepare($insertSql);
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ($entries as $entry) {
            $checksum = sha1($entry['name'] . '|' . $entry['start'] . '|' . $entry['end'] . '|' . $entry['kind']);
            $insert->execute([
                ':source' => $entry['source'],
                ':kind' => $entry['kind'],
                ':name' => $entry['name'],
                ':start_date' => $entry['start'],
                ':end_date' => $entry['end'],
                ':region' => $region,
                ':year' => $year,
                ':checksum' => $checksum,
                ':fetched_at' => $now,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }

        set_setting($pdo, 'last_sync_de-nw_' . $year, (new DateTimeImmutable())->format('c'));
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return fetchFromDb($pdo, $year, $region);
}

function fetchFromDb(PDO $pdo, int $year, string $region): array
{
    $stmt = $pdo->prepare('SELECT kind, name, start_date AS start, end_date AS end FROM holiday_entries WHERE region = ? AND year = ? ORDER BY start_date');
    $stmt->execute([$region, $year]);
    return $stmt->fetchAll();
}

function fetchOpenHolidays(int $year, string $region): array
{
    $base = 'https://openholidaysapi.org';
    $params = http_build_query([
        'countryIsoCode' => 'DE',
        'subdivisionCode' => $region,
        'languageIsoCode' => 'DE',
        'validFrom' => sprintf('%d-01-01', $year),
        'validTo' => sprintf('%d-12-31', $year),
    ]);

    $public = fetchJson("{$base}/PublicHolidays?{$params}");
    $school = fetchJson("{$base}/SchoolHolidays?{$params}");

    $result = [];

    foreach ($public as $item) {
        $name = extractName($item, 'DE');
        if (!$name || empty($item['startDate']) || empty($item['endDate'])) {
            continue;
        }
        $result[] = [
            'source' => 'openholidays',
            'kind' => 'public_holiday',
            'name' => $name,
            'start' => $item['startDate'],
            'end' => $item['endDate'],
        ];
    }

    foreach ($school as $item) {
        $name = extractName($item, 'DE');
        if (!$name || empty($item['startDate']) || empty($item['endDate'])) {
            continue;
        }
        $result[] = [
            'source' => 'openholidays',
            'kind' => 'school_holiday',
            'name' => $name,
            'start' => $item['startDate'],
            'end' => $item['endDate'],
        ];
    }

    return dedupeEntries($result);
}

function fetchFerienApi(int $year, string $region): array
{
    $url = "https://ferien-api.de/api/v1/holidays/NW/{$year}";
    $data = fetchJson($url);
    $result = [];
    foreach ($data as $item) {
        if (empty($item['start']) || empty($item['end']) || empty($item['name'])) {
            continue;
        }
        $start = substr($item['start'], 0, 10);
        $end = substr($item['end'], 0, 10);
        $result[] = [
            'source' => 'ferien_api',
            'kind' => 'school_holiday',
            'name' => $item['name'],
            'start' => $start,
            'end' => $end,
        ];
    }
    return dedupeEntries($result);
}

function fetchNager(int $year, string $region): array
{
    $url = "https://date.nager.at/api/v3/PublicHolidays/{$year}/DE";
    $data = fetchJson($url);
    $result = [];
    foreach ($data as $item) {
        if (empty($item['date']) || empty($item['localName'])) {
            continue;
        }
        if (!empty($item['counties']) && !in_array('DE-NW', $item['counties'], true)) {
            continue;
        }
        $result[] = [
            'source' => 'nager',
            'kind' => 'public_holiday',
            'name' => $item['localName'],
            'start' => $item['date'],
            'end' => $item['date'],
        ];
    }
    return dedupeEntries($result);
}

function fetchJson(string $url): array
{
    $body = http_get($url);
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Ungültige JSON Antwort für ' . $url);
    }
    return $decoded;
}

function http_get(string $url): string
{
    $timeout = 15;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'BazyCalendar/1.0',
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL Fehler: ' . $err);
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 400) {
            throw new RuntimeException('HTTP Fehler ' . $code . ' für ' . $url);
        }
        return $response;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'header' => "User-Agent: BazyCalendar/1.0\r\n",
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        throw new RuntimeException('HTTP Anfrage fehlgeschlagen: ' . $url);
    }
    return $response;
}

function extractName(array $item, string $lang): ?string
{
    if (!empty($item['name']) && is_string($item['name'])) {
        return $item['name'];
    }
    if (empty($item['name']) || !is_array($item['name'])) {
        return null;
    }
    foreach ($item['name'] as $n) {
        if (!empty($n['language']) && strtoupper($n['language']) === strtoupper($lang)) {
            return $n['text'] ?? null;
        }
    }
    return $item['name'][0]['text'] ?? null;
}

function dedupeEntries(array $entries): array
{
    $unique = [];
    foreach ($entries as $entry) {
        $key = implode('|', [$entry['kind'], $entry['name'], $entry['start'], $entry['end']]);
        $unique[$key] = $entry;
    }
    return array_values($unique);
}
