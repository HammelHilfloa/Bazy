<?php
$config = require __DIR__ . '/../../lib/bootstrap.php';

require_once __DIR__ . '/../../lib/Db.php';
require_once __DIR__ . '/../../lib/Response.php';
require_once __DIR__ . '/../../lib/Auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    Response::jsonError('Methode nicht erlaubt.', 405);
}

Auth::requireRole(['admin']);

try {
    $pdo = Db::getConnection($config);
    $stmt = $pdo->query('SELECT id, username, role, is_active, last_login_at, created_at FROM users ORDER BY username ASC');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $users = array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'username' => $row['username'],
            'role' => $row['role'],
            'is_active' => (int) $row['is_active'],
            'last_login_at' => $row['last_login_at'],
            'created_at' => $row['created_at'],
        ];
    }, $rows);

    Response::jsonSuccess(['users' => $users]);
} catch (Exception) {
    Response::jsonError('Benutzer konnten nicht geladen werden.', 500);
}
