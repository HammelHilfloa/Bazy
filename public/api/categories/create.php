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

$name = trim($payload['name'] ?? '');
$colorInput = trim($payload['color'] ?? '');
$sortOrder = isset($payload['sort_order']) ? (int) $payload['sort_order'] : 0;

if ($name === '') {
    Response::jsonError('Name ist erforderlich.', 422);
}

$color = $colorInput === '' ? '#9E9E9E' : $colorInput;
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
    Response::jsonError('Farbwert muss im Format #RRGGBB vorliegen.', 422);
}

try {
    $pdo = Db::getConnection($config);
    $stmt = $pdo->prepare('INSERT INTO categories (name, color, sort_order, is_active, created_at) VALUES (:name, :color, :sort_order, 1, NOW())');
    $stmt->execute([
        'name' => $name,
        'color' => $color,
        'sort_order' => $sortOrder,
    ]);

    $id = (int) $pdo->lastInsertId();
    Response::jsonSuccess([
        'category' => [
            'id' => $id,
            'name' => $name,
            'color' => $color,
            'sort_order' => $sortOrder,
            'is_active' => 1,
        ],
    ], 201);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        Response::jsonError('Kategorie mit diesem Namen existiert bereits.', 409);
    }

    Response::jsonError('Kategorie konnte nicht angelegt werden.', 500);
}
