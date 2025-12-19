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

$username = trim($payload['username'] ?? '');
$role = $payload['role'] ?? '';
$password = $payload['password'] ?? '';

if ($username === '' || strlen($username) < 3 || strlen($username) > 64) {
    Response::jsonError('Benutzername muss zwischen 3 und 64 Zeichen lang sein.', 422);
}

if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
    Response::jsonError('Benutzername darf nur Buchstaben, Zahlen sowie ._- enthalten.', 422);
}

$allowedRoles = ['admin', 'editor', 'viewer'];
if (!in_array($role, $allowedRoles, true)) {
    Response::jsonError('Rolle ist ungültig.', 422);
}

if (!is_string($password) || strlen($password) < 8) {
    Response::jsonError('Passwort muss mindestens 8 Zeichen lang sein.', 422);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo = Db::getConnection($config);
    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, is_active, created_at) VALUES (:username, :password_hash, :role, 1, NOW())');
    $stmt->execute([
        'username' => $username,
        'password_hash' => $passwordHash,
        'role' => $role,
    ]);

    $id = (int) $pdo->lastInsertId();
    $select = $pdo->prepare('SELECT id, username, role, is_active, last_login_at, created_at FROM users WHERE id = :id LIMIT 1');
    $select->execute(['id' => $id]);
    $userRow = $select->fetch(PDO::FETCH_ASSOC);

    $userData = [
        'id' => $id,
        'username' => $username,
        'role' => $role,
        'is_active' => 1,
        'last_login_at' => null,
        'created_at' => $userRow['created_at'] ?? null,
    ];

    AuditLog::record($pdo, 'user', (string) $id, 'create', $currentUser['id'], null, [
        'username' => $username,
        'role' => $role,
        'is_active' => 1,
    ]);

    Response::jsonSuccess(['user' => $userData], 201);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        Response::jsonError('Benutzername ist bereits vergeben.', 409);
    }

    Response::jsonError('Benutzer konnte nicht angelegt werden.', 500);
} catch (Exception) {
    Response::jsonError('Benutzer konnte nicht angelegt werden.', 500);
}
