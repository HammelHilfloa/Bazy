<?php
$config = require __DIR__ . '/../../lib/bootstrap.php';

require_once __DIR__ . '/../../lib/Db.php';
require_once __DIR__ . '/../../lib/Response.php';
require_once __DIR__ . '/../../lib/Auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::jsonError('Methode nicht erlaubt.', 405);
}

Csrf::validatePostRequest();

$payload = $_POST;
if (empty($payload)) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $payload = $decoded;
    }
}

$username = trim($payload['username'] ?? '');
$password = $payload['password'] ?? '';

if ($username === '' || $password === '') {
    Response::jsonError('Bitte Benutzername und Passwort angeben.', 422);
}

try {
    $pdo = Db::getConnection($config);
    $user = Auth::login($pdo, $username, $password);
    Response::jsonSuccess([
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
        ],
    ]);
} catch (AuthException $e) {
    Response::jsonError($e->getMessage(), $e->getStatus());
} catch (Exception) {
    Response::jsonError('Login fehlgeschlagen.', 500);
}
