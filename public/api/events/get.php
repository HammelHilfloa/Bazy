<?php
$config = require __DIR__ . '/../../lib/bootstrap.php';

require_once __DIR__ . '/../../lib/Db.php';
require_once __DIR__ . '/../../lib/Response.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/_functions.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    Response::jsonError('Methode nicht erlaubt.', 405);
}

Auth::requireLogin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    Response::jsonError('Ungültige ID.', 422);
}

try {
    $pdo = Db::getConnection($config);
    $stmt = $pdo->prepare(
        'SELECT e.id, e.category_id, e.title, e.description, e.location_text, e.location_url, e.start_at, e.end_at, e.all_day, e.visibility, e.source, e.external_id, e.created_by, e.updated_by, e.is_deleted, e.created_at, e.updated_at, c.color
         FROM events e
         INNER JOIN categories c ON c.id = e.category_id
         WHERE e.id = :id AND e.is_deleted = 0
         LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        Response::jsonError('Event nicht gefunden.', 404);
    }

    Response::jsonSuccess(['event' => events_format_row($row)]);
} catch (Exception) {
    Response::jsonError('Event konnte nicht geladen werden.', 500);
}
