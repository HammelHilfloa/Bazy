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

$pdo = Db::getConnection($config);

$select = $pdo->prepare('SELECT id, name, color, sort_order, is_active FROM categories WHERE id = :id LIMIT 1');
$select->execute(['id' => $id]);
$existing = $select->fetch(PDO::FETCH_ASSOC);

if (!$existing) {
    Response::jsonError('Kategorie nicht gefunden.', 404);
}

$name = trim($payload['name'] ?? $existing['name']);
$colorInput = trim($payload['color'] ?? $existing['color']);
$sortOrder = isset($payload['sort_order']) ? (int) $payload['sort_order'] : (int) $existing['sort_order'];
$isActive = isset($payload['is_active']) ? (int) ((int) $payload['is_active'] ? 1 : 0) : (int) $existing['is_active'];

if ($name === '') {
    Response::jsonError('Name ist erforderlich.', 422);
}

$color = $colorInput === '' ? ($existing['color'] ?: '#9E9E9E') : $colorInput;
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
    Response::jsonError('Farbwert muss im Format #RRGGBB vorliegen.', 422);
}

try {
    $update = $pdo->prepare('UPDATE categories SET name = :name, color = :color, sort_order = :sort_order, is_active = :is_active WHERE id = :id');
    $update->execute([
        'id' => $id,
        'name' => $name,
        'color' => $color,
        'sort_order' => $sortOrder,
        'is_active' => $isActive,
    ]);

    Response::jsonSuccess([
        'category' => [
            'id' => $id,
            'name' => $name,
            'color' => $color,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ],
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        Response::jsonError('Kategorie mit diesem Namen existiert bereits.', 409);
    }

    Response::jsonError('Kategorie konnte nicht aktualisiert werden.', 500);
}
