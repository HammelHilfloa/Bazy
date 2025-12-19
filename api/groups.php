<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

try {
    $pdo = get_pdo();
} catch (Throwable $e) {
    http_response_code(500);
    error_log('DB connection failed (groups): ' . $e->getMessage());
    echo json_encode(['error' => 'DB-Verbindung fehlgeschlagen']);
    exit;
}

try {
    $stmt = $pdo->query('SELECT id, name FROM `groups` ORDER BY name');
    $groups = $stmt->fetchAll();
    echo json_encode($groups);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Groups fetch error: ' . $e->getMessage());
    echo json_encode(['error' => 'Gruppen konnten nicht geladen werden']);
}
