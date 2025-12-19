<?php
$config = require __DIR__ . '/../../lib/bootstrap.php';

require_once __DIR__ . '/../../lib/Db.php';
require_once __DIR__ . '/../../lib/Response.php';
require_once __DIR__ . '/../../lib/Auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    Response::jsonError('Methode nicht erlaubt.', 405);
}

Auth::requireLogin();

try {
    $pdo = Db::getConnection($config);
    $stmt = $pdo->query('SELECT id, name, color, sort_order, is_active, created_at FROM categories ORDER BY sort_order ASC, name ASC');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $categories = array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'color' => $row['color'],
            'sort_order' => (int) $row['sort_order'],
            'is_active' => (int) $row['is_active'],
            'created_at' => $row['created_at'],
        ];
    }, $rows);

    Response::jsonSuccess(['categories' => $categories]);
} catch (Exception) {
    Response::jsonError('Kategorien konnten nicht geladen werden.', 500);
}
