<?php
$config = require __DIR__ . '/../../lib/bootstrap.php';

require_once __DIR__ . '/../../lib/Db.php';
require_once __DIR__ . '/../../lib/Response.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Csrf.php';
require_once __DIR__ . '/../../lib/Util.php';
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

    $categoryId = isset($payload['category_id']) ? (int) $payload['category_id'] : (int) $existing['category_id'];
    $title = trim($payload['title'] ?? $existing['title']);

    $existingStart = Util::parseDateTime($existing['start_at']);
    $existingEnd = $existing['end_at'] ? Util::parseDateTime($existing['end_at']) : $existingStart;

    if (!$existingStart) {
        Response::jsonError('Vorhandene Startzeit ist ungültig.', 500);
    }

    $startInput = array_key_exists('start_at', $payload) ? trim((string) $payload['start_at']) : '';
    $endProvided = array_key_exists('end_at', $payload);
    $endInput = $endProvided ? trim((string) $payload['end_at']) : '';

    $startAt = $startInput !== '' ? Util::parseDateTime($startInput) : $existingStart;
    if (!$startAt) {
        Response::jsonError('Startzeit ist ungültig.', 422);
    }

    if ($endProvided) {
        if ($endInput === '') {
            $endAt = $startAt;
        } else {
            $endAt = Util::parseDateTime($endInput);
            if (!$endAt) {
                Response::jsonError('Endzeit ist ungültig.', 422);
            }
        }
    } else {
        $endAt = $existingEnd ?: $startAt;
    }

    if ($startAt > $endAt) {
        Response::jsonError('Startzeit darf nicht nach der Endzeit liegen.', 422);
    }

    $allDay = isset($payload['all_day'])
        ? (int) ((int) $payload['all_day'] ? 1 : 0)
        : (int) $existing['all_day'];

    $description = array_key_exists('description', $payload)
        ? trim((string) $payload['description'])
        : ($existing['description'] ?? null);
    if ($description === '') {
        $description = null;
    }

    $locationText = array_key_exists('location_text', $payload)
        ? trim((string) $payload['location_text'])
        : ($existing['location_text'] ?? null);
    if ($locationText === '') {
        $locationText = null;
    }

    $locationUrlInput = array_key_exists('location_url', $payload)
        ? trim((string) $payload['location_url'])
        : ($existing['location_url'] ?? '');
    $locationUrl = $locationUrlInput === '' ? null : $locationUrlInput;
    if ($locationUrl !== null && !Util::validateUrl($locationUrl)) {
        Response::jsonError('Ort-URL ist ungültig.', 422);
    }

    if ($categoryId <= 0) {
        Response::jsonError('Kategorie ist erforderlich.', 422);
    }

    if ($title === '') {
        Response::jsonError('Titel ist erforderlich.', 422);
    }

    $categoryCheck = $pdo->prepare('SELECT id FROM categories WHERE id = :id LIMIT 1');
    $categoryCheck->execute(['id' => $categoryId]);
    if (!$categoryCheck->fetch(PDO::FETCH_ASSOC)) {
        Response::jsonError('Kategorie nicht gefunden.', 404);
    }

    $update = $pdo->prepare(
        'UPDATE events
         SET category_id = :category_id,
             title = :title,
             description = :description,
             location_text = :location_text,
             location_url = :location_url,
             start_at = :start_at,
             end_at = :end_at,
             all_day = :all_day,
             updated_by = :updated_by
         WHERE id = :id'
    );

    $update->execute([
        'id' => $id,
        'category_id' => $categoryId,
        'title' => $title,
        'description' => $description,
        'location_text' => $locationText,
        'location_url' => $locationUrl,
        'start_at' => $startAt->format('Y-m-d H:i:s'),
        'end_at' => $endAt->format('Y-m-d H:i:s'),
        'all_day' => $allDay,
        'updated_by' => $currentUser['id'],
    ]);

    $select->execute(['id' => $id]);
    $updated = $select->fetch(PDO::FETCH_ASSOC);

    $after = $updated ? events_audit_payload($updated) : null;
    AuditLog::record($pdo, 'event', (string) $id, 'update', $currentUser['id'], $before, $after);

    Response::jsonSuccess(['event' => $updated ? events_format_row($updated) : ['id' => $id]]);
} catch (Exception $e) {
    Logger::error('Event konnte nicht aktualisiert werden', ['id' => $id, 'error' => $e->getMessage()]);
    Response::jsonError('Event konnte nicht aktualisiert werden.', 500);
}
