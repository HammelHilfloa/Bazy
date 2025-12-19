<?php
$config = require __DIR__ . '/../../lib/bootstrap.php';

require_once __DIR__ . '/../../lib/Db.php';
require_once __DIR__ . '/../../lib/Response.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Csrf.php';
require_once __DIR__ . '/../../lib/AuditLog.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::jsonError('Methode nicht erlaubt.', 405);
}

Csrf::validatePostRequest();
$currentUser = Auth::requireRole(['admin']);

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
    Response::jsonError('Ungültige Benutzer-ID.', 422);
}

try {
    $pdo = Db::getConnection($config);
    $select = $pdo->prepare('SELECT id, username, role, is_active, last_login_at, created_at FROM users WHERE id = :id LIMIT 1');
    $select->execute(['id' => $id]);
    $existing = $select->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        Response::jsonError('Benutzer wurde nicht gefunden.', 404);
    }

    if ($currentUser['id'] === (int) $existing['id']) {
        Response::jsonError('Das eigene Konto kann nicht deaktiviert werden.', 400);
    }

    $beforeData = [
        'username' => $existing['username'],
        'role' => $existing['role'],
        'is_active' => (int) $existing['is_active'],
        'last_login_at' => $existing['last_login_at'],
    ];

    $update = $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = :id');
    $update->execute(['id' => $id]);

    AuditLog::record($pdo, 'user', (string) $id, 'delete', $currentUser['id'], $beforeData, [
        'username' => $existing['username'],
        'role' => $existing['role'],
        'is_active' => 0,
        'last_login_at' => $existing['last_login_at'],
    ]);

    Response::jsonSuccess(['message' => 'Benutzer wurde deaktiviert.']);
} catch (Exception) {
    Response::jsonError('Benutzer konnte nicht deaktiviert werden.', 500);
}
