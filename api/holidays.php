<?php
declare(strict_types=1);

if (!defined('APP_BASE')) {
    define('APP_BASE', realpath(__DIR__ . '/..') ?: (__DIR__ . '/..'));
}

header('Content-Type: application/json');

$year = isset($_GET['year']) ? (int) $_GET['year'] : 2026;
if ($year < 2000 || $year > 2100) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültiges Jahr']);
    exit;
}

$cacheFile = sprintf('%s/cache/holidays_%d_DE-NW.json', APP_BASE, $year);
$fallbackFile = sprintf('%s/data/holidays_%d_DE-NW_fallback.json', APP_BASE, $year);
$cacheTtl = 60 * 60 * 24; // 24h
$validFrom = sprintf('%d-01-01', $year);
$validTo = sprintf('%d-12-31', $year);

if (!is_dir(dirname($cacheFile))) {
    mkdir(dirname($cacheFile), 0775, true);
}

if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
    readfile($cacheFile);
    exit;
}

$baseUrl = 'https://openholidaysapi.org';
$country = 'DE';
$subdivision = 'DE-NW';
$lang = 'DE';

$publicUrl = sprintf(
    '%s/PublicHolidays?countryIsoCode=%s&subdivisionCode=%s&languageIsoCode=%s&validFrom=%s&validTo=%s',
    $baseUrl,
    $country,
    $subdivision,
    $lang,
    $validFrom,
    $validTo
);

$schoolUrl = sprintf(
    '%s/SchoolHolidays?countryIsoCode=%s&subdivisionCode=%s&languageIsoCode=%s&validFrom=%s&validTo=%s',
    $baseUrl,
    $country,
    $subdivision,
    $lang,
    $validFrom,
    $validTo
);

function fetchJsonFromApi(string $url): array
{
    $context = stream_context_create([
        'http' => ['timeout' => 10],
        'https' => ['timeout' => 10],
    ]);

    $json = @file_get_contents($url, false, $context);
    if ($json === false) {
        return [];
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function readJsonFile(string $file): array
{
    if (!is_file($file)) {
        return [];
    }

    $contents = file_get_contents($file);
    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : [];
}

function extractName(array $item): string
{
    if (isset($item['name'])) {
        if (is_string($item['name'])) {
            return $item['name'];
        }
        if (isset($item['name'][0]['text'])) {
            return $item['name'][0]['text'];
        }
        if (isset($item['name']['text'])) {
            return $item['name']['text'];
        }
    }

    if (isset($item['description']) && is_string($item['description'])) {
        return $item['description'];
    }

    return 'Unbenannt';
}

function extractDateValue($value): ?string
{
    if (is_string($value)) {
        return substr($value, 0, 10);
    }
    if (is_array($value)) {
        if (isset($value['date'])) {
            return substr($value['date'], 0, 10);
        }
        if (isset($value['startDate'])) {
            return substr($value['startDate'], 0, 10);
        }
    }
    return null;
}

function normalizeHolidays(array $items, string $type): array
{
    $normalized = [];
    foreach ($items as $item) {
        $start = extractDateValue($item['startDate'] ?? null);
        $end = extractDateValue($item['endDate'] ?? null);
        if (!$start || !$end) {
            continue;
        }

        $normalized[] = [
            'type' => $type,
            'name' => extractName($item),
            'start' => $start,
            'end' => $end,
        ];
    }
    return $normalized;
}

$public = fetchJsonFromApi($publicUrl);
$schools = fetchJsonFromApi($schoolUrl);

$normalized = array_merge(
    normalizeHolidays($public, 'holiday'),
    normalizeHolidays($schools, 'school_holiday')
);

if (!empty($normalized)) {
    file_put_contents($cacheFile, json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode($normalized, JSON_UNESCAPED_UNICODE);
    exit;
}

if (file_exists($cacheFile)) {
    readfile($cacheFile);
    exit;
}

$fallbackData = readJsonFile($fallbackFile);
if (!empty($fallbackData)) {
    file_put_contents($cacheFile, json_encode($fallbackData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode($fallbackData, JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(502);
echo json_encode(['error' => 'Konnte keine Feiertage abrufen']);
