<?php
$cacheFile = __DIR__ . '/../cache/holidays_2026.json';
$cacheTtl = 60 * 60 * 24; // 24h
$validFrom = '2026-01-01';
$validTo = '2026-12-31';

header('Content-Type: application/json');

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
    echo json_encode($normalized);
    exit;
}

// Fallback: versuche alten Cache
if (file_exists($cacheFile)) {
    readfile($cacheFile);
    exit;
}

http_response_code(502);
echo json_encode(['error' => 'Konnte keine Feiertage abrufen']);
