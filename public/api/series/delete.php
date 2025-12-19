<?php
$config = require __DIR__ . '/../../lib/bootstrap.php';

require_once __DIR__ . '/../../lib/Db.php';
require_once __DIR__ . '/../../lib/Response.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Csrf.php';
require_once __DIR__ . '/../../lib/AuditLog.php';
require_once __DIR__ . '/_functions.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::jsonError('Methode nicht erlaubt.', 405);
}

Csrf::validatePostRequest();
$currentUser = Auth::requireRole(['editor']);

$payload = series_load_payload();
$id = isset($payload['id']) ? (int) $payload['id'] : 0;

if ($id <= 0) {
    Response::jsonError('Ungültige ID.', 422);
}

try {
    $pdo = Db::getConnection($config);

    $select = $pdo->prepare('SELECT * FROM event_series WHERE id = :id AND is_active = 1 LIMIT 1');
    $select->execute(['id' => $id]);
    $existing = $select->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        Response::jsonError('Serie nicht gefunden.', 404);
    }

    $before = series_audit_payload($existing);

    $update = $pdo->prepare('UPDATE event_series SET is_active = 0, updated_by = :updated_by WHERE id = :id');
    $update->execute([
        'id' => $id,
        'updated_by' => $currentUser['id'],
    ]);

    $after = $before;
    $after['is_active'] = 0;
    $after['updated_by'] = $currentUser['id'];

    AuditLog::record($pdo, 'series', (string) $id, 'delete', $currentUser['id'], $before, $after);

    Response::jsonSuccess(['message' => 'Serie deaktiviert.', 'id' => $id]);
} catch (Exception) {
    Response::jsonError('Serie konnte nicht gelöscht werden.', 500);
}
