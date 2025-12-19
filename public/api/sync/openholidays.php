<?php
$config = require __DIR__ . '/../../lib/bootstrap.php';

require_once __DIR__ . '/../../lib/Db.php';
require_once __DIR__ . '/../../lib/Response.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Csrf.php';
require_once __DIR__ . '/../../lib/Util.php';
require_once __DIR__ . '/../../lib/AuditLog.php';
require_once __DIR__ . '/../../lib/OpenHolidaysClient.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::jsonError('Methode nicht erlaubt.', 405);
}

Csrf::validatePostRequest();
$currentUser = Auth::requireRole('admin');

$payload = sync_load_payload();

$nowYear = (int) date('Y');
$yearFrom = isset($payload['year_from']) ? (int) $payload['year_from'] : $nowYear;
$yearTo = isset($payload['year_to']) ? (int) $payload['year_to'] : ($yearFrom + 1);

if ($yearFrom < 2000 || $yearTo < 2000) {
    Response::jsonError('Jahre müssen ab 2000 liegen.', 422);
}

if ($yearTo < $yearFrom) {
    Response::jsonError('year_to darf nicht kleiner als year_from sein.', 422);
}

$validFrom = sprintf('%04d-01-01', $yearFrom);
$validTo = sprintf('%04d-12-31', $yearTo);

$report = [
    'imported' => 0,
    'updated' => 0,
    'unchanged' => 0,
    'errors' => [],
];

try {
    $client = new OpenHolidaysClient('DE', 'DE-NW', 'DE');
    try {
        $publicHolidays = $client->fetchPublicHolidays($validFrom, $validTo);
    } catch (Throwable $e) {
        $publicHolidays = [];
        $report['errors'][] = 'Fehler beim Laden der Feiertage: ' . $e->getMessage();
    }

    try {
        $schoolHolidays = $client->fetchSchoolHolidays($validFrom, $validTo);
    } catch (Throwable $e) {
        $schoolHolidays = [];
        $report['errors'][] = 'Fehler beim Laden der Ferien: ' . $e->getMessage();
    }

    if (empty($publicHolidays) && empty($schoolHolidays)) {
        Response::jsonError('Keine Daten von OpenHolidays erhalten.', 502, ['report' => $report]);
    }

    $pdo = Db::getConnection($config);
    $pdo->beginTransaction();

    $categoryFeiertage = sync_ensure_category($pdo, 'Feiertage', '#1E88E5', 900);
    $categoryFerien = sync_ensure_category($pdo, 'Ferien', '#26A69A', 910);

    $rows = [];
    foreach ($publicHolidays as $item) {
        $mapped = sync_map_holiday($item, $categoryFeiertage, 'Feiertag', $report);
        if ($mapped) {
            $rows[] = $mapped;
        }
    }
    foreach ($schoolHolidays as $item) {
        $mapped = sync_map_holiday($item, $categoryFerien, 'Ferien', $report);
        if ($mapped) {
            $rows[] = $mapped;
        }
    }

    if (empty($rows)) {
        $pdo->rollBack();
        Response::jsonError('Keine gültigen Events zum Import gefunden.', 422, ['report' => $report]);
    }

    $selectStmt = $pdo->prepare(
        'SELECT id, category_id, title, description, start_at, end_at, all_day, is_deleted
         FROM events
         WHERE source = "openholidays" AND external_id = :external_id
         LIMIT 1'
    );

    $updateStmt = $pdo->prepare(
        'UPDATE events
         SET category_id = :category_id,
             title = :title,
             description = :description,
             start_at = :start_at,
             end_at = :end_at,
             all_day = 1,
             visibility = "internal",
             is_deleted = 0,
             updated_by = :updated_by,
             updated_at = NOW()
         WHERE id = :id'
    );

    $insertStmt = $pdo->prepare(
        'INSERT INTO events (category_id, title, description, location_text, location_url, start_at, end_at, all_day, visibility, source, external_id, created_by, updated_by, created_at)
         VALUES (:category_id, :title, :description, NULL, NULL, :start_at, :end_at, 1, "internal", "openholidays", :external_id, :user_id, :user_id, NOW())'
    );

    foreach ($rows as $row) {
        try {
            $selectStmt->execute(['external_id' => $row['external_id']]);
            $existing = $selectStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $needsUpdate = sync_needs_update($existing, $row);
                if ($needsUpdate) {
                    $updateStmt->execute([
                        'id' => $existing['id'],
                        'category_id' => $row['category_id'],
                        'title' => $row['title'],
                        'description' => $row['description'],
                        'start_at' => $row['start_at'],
                        'end_at' => $row['end_at'],
                        'updated_by' => $currentUser['id'],
                    ]);
                    $report['updated']++;
                } else {
                    $report['unchanged']++;
                }
                continue;
            }

            $insertStmt->execute([
                'category_id' => $row['category_id'],
                'title' => $row['title'],
                'description' => $row['description'],
                'start_at' => $row['start_at'],
                'end_at' => $row['end_at'],
                'external_id' => $row['external_id'],
                'user_id' => $currentUser['id'],
            ]);
            $report['imported']++;
        } catch (Throwable $e) {
            $report['errors'][] = 'Fehler bei Event "' . $row['title'] . '": ' . $e->getMessage();
        }
    }

    $pdo->commit();

    AuditLog::record(
        $pdo,
        'sync',
        'openholidays',
        'sync',
        $currentUser['id'],
        null,
        [
            'year_from' => $yearFrom,
            'year_to' => $yearTo,
            'report' => $report,
        ]
    );

    Response::jsonSuccess(['report' => $report]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $report['errors'][] = $e->getMessage();
    Response::jsonError('Sync fehlgeschlagen.', 500, ['report' => $report]);
}

/**
 * @return array<string,mixed>
 */
function sync_load_payload(): array
{
    if (!empty($_POST)) {
        return $_POST;
    }

    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return $decoded;
    }

    return [];
}

function sync_ensure_category(PDO $pdo, string $name, string $color, int $sortOrder): int
{
    $stmt = $pdo->prepare('SELECT id, color, sort_order, is_active FROM categories WHERE name = :name LIMIT 1');
    $stmt->execute(['name' => $name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        if ((int) $row['is_active'] === 0 || $row['color'] !== $color || (int) $row['sort_order'] !== $sortOrder) {
            $update = $pdo->prepare('UPDATE categories SET color = :color, sort_order = :sort_order, is_active = 1 WHERE id = :id');
            $update->execute(['color' => $color, 'sort_order' => $sortOrder, 'id' => $row['id']]);
        }

        return (int) $row['id'];
    }

    $insert = $pdo->prepare('INSERT INTO categories (name, color, sort_order, is_active, created_at) VALUES (:name, :color, :sort_order, 1, NOW())');
    $insert->execute(['name' => $name, 'color' => $color, 'sort_order' => $sortOrder]);

    return (int) $pdo->lastInsertId();
}

/**
 * @param array<string,mixed> $item
 * @param array{imported:int,updated:int,unchanged:int,errors:array<int,string>} $report
 * @return array<string,mixed>|null
 */
function sync_map_holiday(array $item, int $categoryId, string $fallbackTitle, array &$report): ?array
{
    $startDate = sync_normalize_date($item['startDate'] ?? $item['start'] ?? null);
    $endDate = sync_normalize_date($item['endDate'] ?? $item['end'] ?? null) ?? $startDate;

    if ($startDate === null) {
        $report['errors'][] = 'Eintrag ohne gültiges Startdatum übersprungen.';
        return null;
    }

    $title = sync_pick_text($item['name'] ?? null) ?: $fallbackTitle;
    $description = sync_pick_text($item['note'] ?? ($item['notes'] ?? null));

    $externalId = $item['id'] ?? ($item['sourceHolidayId'] ?? ($item['sourceSchoolHolidayId'] ?? null));
    if (!$externalId) {
        $externalId = hash('sha256', $fallbackTitle . '|' . $startDate . '|' . ($endDate ?? $startDate) . '|' . $title);
    }

    $safeTitle = mb_substr((string) $title, 0, 255);
    $safeDescription = $description !== null ? mb_substr((string) $description, 0, 1000) : null;

    return [
        'category_id' => $categoryId,
        'title' => $safeTitle,
        'description' => $safeDescription,
        'start_at' => $startDate . ' 00:00:00',
        'end_at' => ($endDate ?? $startDate) . ' 23:59:59',
        'external_id' => substr((string) $externalId, 0, 120),
    ];
}

function sync_normalize_date(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    $clean = trim((string) $value);
    $candidates = [$clean];
    if (strlen($clean) >= 10) {
        $candidates[] = substr($clean, 0, 10);
    }

    foreach ($candidates as $candidate) {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $candidate)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $candidate)
            ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $candidate)
            ?: DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $candidate);

        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d');
        }

        try {
            $dt = new DateTimeImmutable($candidate);
            return $dt->format('Y-m-d');
        } catch (Exception) {
            continue;
        }
    }

    return null;
}

/**
 * @param mixed $value
 */
function sync_pick_text($value, string $language = 'DE'): ?string
{
    if (is_string($value)) {
        $text = trim($value);
        return $text === '' ? null : $text;
    }

    if (!is_array($value)) {
        return null;
    }

    $lang = strtoupper($language);
    foreach ($value as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $entryLang = strtoupper((string) ($entry['language'] ?? ($entry['languageCode'] ?? '')));
        if ($entryLang === $lang && isset($entry['text'])) {
            $text = trim((string) $entry['text']);
            if ($text !== '') {
                return $text;
            }
        }
    }

    foreach ($value as $entry) {
        if (is_array($entry) && isset($entry['text'])) {
            $text = trim((string) $entry['text']);
            if ($text !== '') {
                return $text;
            }
        }
    }

    return null;
}

/**
 * @param array<string,mixed> $existing
 * @param array<string,mixed> $incoming
 */
function sync_needs_update(array $existing, array $incoming): bool
{
    if ((int) $existing['is_deleted'] === 1) {
        return true;
    }

    foreach (['category_id', 'title', 'description', 'start_at', 'end_at'] as $key) {
        $existingValue = $existing[$key] ?? null;
        $incomingValue = $incoming[$key] ?? null;
        if ((string) $existingValue !== (string) $incomingValue) {
            return true;
        }
    }

    return false;
}
