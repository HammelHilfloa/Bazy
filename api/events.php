<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $pdo = get_pdo();
} catch (Throwable $e) {
    http_response_code(500);
    error_log('DB connection failed (events): ' . $e->getMessage());
    echo json_encode(['error' => 'DB-Verbindung fehlgeschlagen']);
    exit;
}

switch ($method) {
    case 'GET':
        handleGet($pdo);
        break;
    case 'POST':
        handleCreate($pdo);
        break;
    case 'DELETE':
        handleDelete($pdo);
        break;
    default:
        http_response_code(405);
        header('Allow: GET, POST, DELETE');
        echo json_encode(['error' => 'Methode nicht erlaubt']);
}

function handleGet(PDO $pdo): void
{
    $start = $_GET['start'] ?? null;
    $end = $_GET['end'] ?? null;

    if (!$start || !$end) {
        http_response_code(400);
        echo json_encode(['error' => 'start und end sind erforderlich']);
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT e.id, e.title, e.start_at, e.end_at, e.location, e.notes, e.group_id, g.name AS group_name
             FROM events e
             JOIN `groups` g ON g.id = e.group_id
             WHERE e.end_at >= :start AND e.start_at <= :end
             ORDER BY e.start_at'
        );
        $stmt->execute([
            ':start' => $start,
            ':end' => $end,
        ]);
        $events = $stmt->fetchAll();
        echo json_encode($events);
    } catch (Throwable $e) {
        http_response_code(500);
        error_log('Events fetch error: ' . $e->getMessage());
        echo json_encode(['error' => 'Termine konnten nicht geladen werden']);
    }
}

function handleCreate(PDO $pdo): void
{
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige JSON Eingabe']);
        return;
    }

    $title = trim((string) ($payload['title'] ?? ''));
    $groupId = (int) ($payload['group_id'] ?? 0);
    $startAt = trim((string) ($payload['start_at'] ?? ''));
    $endAt = trim((string) ($payload['end_at'] ?? ''));
    $location = trim((string) ($payload['location'] ?? '')) ?: null;
    $notes = trim((string) ($payload['notes'] ?? '')) ?: null;

    if ($title === '' || !$groupId || $startAt === '' || $endAt === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Titel, Gruppe, Start- und Endzeit sind erforderlich']);
        return;
    }

    if (strtotime($endAt) < strtotime($startAt)) {
        http_response_code(400);
        echo json_encode(['error' => 'Endzeit darf nicht vor der Startzeit liegen']);
        return;
    }

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM `groups` WHERE id = ?');
        $stmt->execute([$groupId]);
        if ((int) $stmt->fetchColumn() === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Ungültige Gruppe']);
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO events (group_id, title, start_at, end_at, location, notes, created_at, updated_at)
             VALUES (:group_id, :title, :start_at, :end_at, :location, :notes, :created_at, :updated_at)'
        );
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $insert->execute([
            ':group_id' => $groupId,
            ':title' => $title,
            ':start_at' => $startAt,
            ':end_at' => $endAt,
            ':location' => $location,
            ':notes' => $notes,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        echo json_encode(['id' => (int) $pdo->lastInsertId()]);
    } catch (Throwable $e) {
        http_response_code(500);
        error_log('Event create error: ' . $e->getMessage());
        echo json_encode(['error' => 'Termin konnte nicht gespeichert werden']);
    }
}

function handleDelete(PDO $pdo): void
{
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Termin-ID']);
        return;
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM events WHERE id = ?');
        $stmt->execute([$id]);
        echo json_encode(['deleted' => $stmt->rowCount() > 0]);
    } catch (Throwable $e) {
        http_response_code(500);
        error_log('Event delete error: ' . $e->getMessage());
        echo json_encode(['error' => 'Termin konnte nicht gelöscht werden']);
    }
}
