<?php
$config = require __DIR__ . '/../../lib/bootstrap.php';

require_once __DIR__ . '/../../lib/Db.php';
require_once __DIR__ . '/../../lib/Response.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Util.php';
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

$filters = ['e.is_deleted = 0', 'e.start_at <= :to', 'COALESCE(e.end_at, e.start_at) >= :from'];

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
    Response::jsonSuccess(['events' => $events]);
} catch (Exception) {
    Response::jsonError('Events konnten nicht geladen werden.', 500);
}
