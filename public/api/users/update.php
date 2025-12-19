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
$role = $payload['role'] ?? null;
$isActive = $payload['is_active'] ?? null;
$newPassword = $payload['password'] ?? '';

if ($id <= 0) {
    Response::jsonError('Ungültige Benutzer-ID.', 422);
}

$allowedRoles = ['admin', 'editor', 'viewer'];
if (!in_array($role, $allowedRoles, true)) {
    Response::jsonError('Rolle ist ungültig.', 422);
}

if (!in_array($isActive, [0, 1, '0', '1', true, false], true)) {
    Response::jsonError('Status ist ungültig.', 422);
}

$isActive = (int) $isActive;

$passwordChanged = is_string($newPassword) && $newPassword !== '';
if ($passwordChanged && strlen($newPassword) < 8) {
    Response::jsonError('Neues Passwort muss mindestens 8 Zeichen lang sein.', 422);
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
        if ($isActive === 0) {
            Response::jsonError('Das eigene Konto kann nicht deaktiviert werden.', 400);
        }
        if ($role !== 'admin') {
            Response::jsonError('Die eigene Rolle kann nicht geändert werden.', 400);
        }
    }

    $beforeData = [
        'username' => $existing['username'],
        'role' => $existing['role'],
        'is_active' => (int) $existing['is_active'],
        'last_login_at' => $existing['last_login_at'],
    ];

    $sql = 'UPDATE users SET role = :role, is_active = :is_active';
    $params = [
        'role' => $role,
        'is_active' => $isActive,
        'id' => $id,
    ];

    if ($passwordChanged) {
        $sql .= ', password_hash = :password_hash';
        $params['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    }

    $sql .= ' WHERE id = :id';
    $update = $pdo->prepare($sql);
    $update->execute($params);

    $afterData = [
        'username' => $existing['username'],
        'role' => $role,
        'is_active' => $isActive,
        'last_login_at' => $existing['last_login_at'],
        'password_changed' => $passwordChanged,
    ];

    AuditLog::record($pdo, 'user', (string) $id, 'update', $currentUser['id'], $beforeData, $afterData);

    Response::jsonSuccess([
        'user' => [
            'id' => $id,
            'username' => $existing['username'],
            'role' => $role,
            'is_active' => $isActive,
            'last_login_at' => $existing['last_login_at'],
            'created_at' => $existing['created_at'],
        ],
    ]);
} catch (Exception) {
    Response::jsonError('Benutzer konnte nicht aktualisiert werden.', 500);
}
