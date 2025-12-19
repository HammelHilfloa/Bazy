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

$categoryId = isset($payload['category_id']) ? (int) $payload['category_id'] : 0;
$title = trim($payload['title'] ?? '');
$startInput = trim($payload['start_at'] ?? '');
$endInput = isset($payload['end_at']) ? trim((string) $payload['end_at']) : '';
$allDay = isset($payload['all_day']) ? (int) ((int) $payload['all_day'] ? 1 : 0) : 0;
$description = isset($payload['description']) ? trim((string) $payload['description']) : null;
$locationText = isset($payload['location_text']) ? trim((string) $payload['location_text']) : null;
$locationUrlInput = isset($payload['location_url']) ? trim((string) $payload['location_url']) : '';

if ($categoryId <= 0) {
    Response::jsonError('Kategorie ist erforderlich.', 422);
}

if ($title === '') {
    Response::jsonError('Titel ist erforderlich.', 422);
}

if ($startInput === '') {
    Response::jsonError('Startzeit ist erforderlich.', 422);
}

$startAt = Util::parseDateTime($startInput);
if (!$startAt) {
    Response::jsonError('Startzeit ist ungültig.', 422);
}

if ($endInput === '') {
    $endAt = $startAt;
} else {
    $endAt = Util::parseDateTime($endInput);
    if (!$endAt) {
        Response::jsonError('Endzeit ist ungültig.', 422);
    }
}

if ($startAt > $endAt) {
    Response::jsonError('Startzeit darf nicht nach der Endzeit liegen.', 422);
}

$locationUrl = $locationUrlInput === '' ? null : $locationUrlInput;
if ($locationUrl !== null && !Util::validateUrl($locationUrl)) {
    Response::jsonError('Ort-URL ist ungültig.', 422);
}

if ($description === '') {
    $description = null;
}

if ($locationText === '') {
    $locationText = null;
}

try {
    $pdo = Db::getConnection($config);

    $categoryCheck = $pdo->prepare('SELECT id FROM categories WHERE id = :id LIMIT 1');
    $categoryCheck->execute(['id' => $categoryId]);
    if (!$categoryCheck->fetch(PDO::FETCH_ASSOC)) {
        Response::jsonError('Kategorie nicht gefunden.', 404);
    }

    $insert = $pdo->prepare(
        'INSERT INTO events (category_id, title, description, location_text, location_url, start_at, end_at, all_day, visibility, source, created_by, updated_by, created_at)
         VALUES (:category_id, :title, :description, :location_text, :location_url, :start_at, :end_at, :all_day, "internal", "manual", :created_by, :updated_by, NOW())'
    );

    $insert->execute([
        'category_id' => $categoryId,
        'title' => $title,
        'description' => $description,
        'location_text' => $locationText,
        'location_url' => $locationUrl,
        'start_at' => $startAt->format('Y-m-d H:i:s'),
        'end_at' => $endAt->format('Y-m-d H:i:s'),
        'all_day' => $allDay,
        'created_by' => $currentUser['id'],
        'updated_by' => $currentUser['id'],
    ]);

    $id = (int) $pdo->lastInsertId();

    $select = $pdo->prepare(
        'SELECT e.id, e.category_id, e.title, e.description, e.location_text, e.location_url, e.start_at, e.end_at, e.all_day, e.visibility, e.source, e.external_id, e.created_by, e.updated_by, e.is_deleted, e.created_at, e.updated_at, c.color
         FROM events e
         INNER JOIN categories c ON c.id = e.category_id
         WHERE e.id = :id
         LIMIT 1'
    );
    $select->execute(['id' => $id]);
    $row = $select->fetch(PDO::FETCH_ASSOC);

    $after = $row ? events_audit_payload($row) : null;
    AuditLog::record($pdo, 'event', (string) $id, 'create', $currentUser['id'], null, $after);

    Response::jsonSuccess(['event' => $row ? events_format_row($row) : ['id' => $id]], 201);
} catch (Exception $e) {
    Logger::error('Event konnte nicht erstellt werden', ['error' => $e->getMessage()]);
    Response::jsonError('Event konnte nicht erstellt werden.', 500);
}
