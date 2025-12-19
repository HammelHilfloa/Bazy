<?php
$config = require __DIR__ . '/../../lib/bootstrap.php';

require_once __DIR__ . '/../../lib/Db.php';
require_once __DIR__ . '/../../lib/Response.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Csrf.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::jsonError('Methode nicht erlaubt.', 405);
}

Csrf::validatePostRequest();
Auth::requireRole(['editor']);

$payload = $_POST;
if (empty($payload)) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $payload = $decoded;
    }
}

$id = isset($payload['id']) ? (int) $payload['id'] : 0;
if ($id <= 0) {
    Response::jsonError('Ungültige ID.', 422);
}

try {
    $pdo = Db::getConnection($config);

    $select = $pdo->prepare('SELECT id FROM categories WHERE id = :id LIMIT 1');
    $select->execute(['id' => $id]);
    $existing = $select->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        Response::jsonError('Kategorie nicht gefunden.', 404);
    }

    $update = $pdo->prepare('UPDATE categories SET is_active = 0 WHERE id = :id');
    $update->execute(['id' => $id]);

    Response::jsonSuccess(['message' => 'Kategorie deaktiviert.', 'id' => $id]);
} catch (Exception) {
    Response::jsonError('Kategorie konnte nicht gelöscht werden.', 500);
}
