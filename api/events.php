<?php
session_start();

$eventsFile = __DIR__ . '/../data/events_2026.json';
$backupDir = __DIR__ . '/../data/backups';

if (!file_exists($eventsFile)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Events-Datei fehlt.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    readfile($eventsFile);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    exit;
}

$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!$csrfHeader || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfHeader)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'CSRF Prüfung fehlgeschlagen']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Ungültiges JSON']);
    exit;
}

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0775, true);
}

$fileHandle = fopen($eventsFile, 'c+');
if (!$fileHandle) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Datei konnte nicht geöffnet werden']);
    exit;
}

if (!flock($fileHandle, LOCK_EX)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Datei konnte nicht gesperrt werden']);
    fclose($fileHandle);
    exit;
}

$existingContent = stream_get_contents($fileHandle);
$timestamp = date('Ymd_His');
$backupPath = $backupDir . "/events_2026_{$timestamp}.json";
file_put_contents($backupPath, $existingContent);

$encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($encoded === false) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'JSON Kodierung fehlgeschlagen']);
    flock($fileHandle, LOCK_UN);
    fclose($fileHandle);
    exit;
}

rewind($fileHandle);
ftruncate($fileHandle, 0);
fwrite($fileHandle, $encoded);
fflush($fileHandle);
flock($fileHandle, LOCK_UN);
fclose($fileHandle);

header('Content-Type: application/json');
echo json_encode(['status' => 'ok']);
