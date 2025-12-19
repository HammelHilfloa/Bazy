<?php
$config = require __DIR__ . '/../../lib/bootstrap.php';

require_once __DIR__ . '/../../lib/Db.php';
require_once __DIR__ . '/../../lib/Response.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Csrf.php';
require_once __DIR__ . '/../../lib/AuditLog.php';
require_once __DIR__ . '/../../lib/Logger.php';
require_once __DIR__ . '/_functions.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::jsonError('Methode nicht erlaubt.', 405);
}

Csrf::validatePostRequest();
$currentUser = Auth::requireRole(['editor']);

$payload = events_load_payload();
$id = isset($payload['id']) ? (int) $payload['id'] : 0;

if ($id <= 0) {
    Response::jsonError('Ungültige ID.', 422);
}

try {
    $pdo = Db::getConnection($config);

    $select = $pdo->prepare(
        'SELECT e.id, e.category_id, e.title, e.description, e.location_text, e.location_url, e.start_at, e.end_at, e.all_day, e.visibility, e.source, e.external_id, e.created_by, e.updated_by, e.is_deleted, e.created_at, e.updated_at, c.color
         FROM events e
         INNER JOIN categories c ON c.id = e.category_id
         WHERE e.id = :id AND e.is_deleted = 0
         LIMIT 1'
    );
    $select->execute(['id' => $id]);
    $existing = $select->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        Response::jsonError('Event nicht gefunden.', 404);
    }

    if (($existing['source'] ?? 'manual') !== 'manual') {
        Response::jsonError('Systemtermine können nicht bearbeitet werden.', 403);
    }

    $before = events_audit_payload($existing);

    $update = $pdo->prepare('UPDATE events SET is_deleted = 1, updated_by = :updated_by WHERE id = :id');
    $update->execute([
        'id' => $id,
        'updated_by' => $currentUser['id'],
    ]);

    $after = $before;
    $after['is_deleted'] = 1;
    AuditLog::record($pdo, 'event', (string) $id, 'delete', $currentUser['id'], $before, $after);

    Response::jsonSuccess(['message' => 'Event gelöscht.', 'id' => $id]);
} catch (Exception $e) {
    Logger::error('Event konnte nicht gelöscht werden', ['id' => $id, 'error' => $e->getMessage()]);
    Response::jsonError('Event konnte nicht gelöscht werden.', 500);
}
