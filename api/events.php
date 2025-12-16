<?php
declare(strict_types=1);

if (!defined('APP_BASE')) {
    define('APP_BASE', realpath(__DIR__ . '/..') ?: (__DIR__ . '/..'));
}

session_start();

$eventsFile = APP_BASE . '/data/events_2026.json';
$backupDir = APP_BASE . '/data/backups';

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

function ensure_events_file(string $file): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    if (!file_exists($file)) {
        file_put_contents($file, json_encode([], JSON_PRETTY_PRINT));
    }
}

ensure_events_file($eventsFile);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response(json_decode(file_get_contents($eventsFile), true) ?? []);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    exit;
}

$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!$csrfHeader || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfHeader)) {
    json_response(['error' => 'CSRF Prüfung fehlgeschlagen'], 403);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    json_response(['error' => 'Ungültiges JSON'], 400);
    exit;
}

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0775, true);
}

$fileHandle = fopen($eventsFile, 'c+');
if (!$fileHandle) {
    json_response(['error' => 'Datei konnte nicht geöffnet werden'], 500);
    exit;
}

if (!flock($fileHandle, LOCK_EX)) {
    fclose($fileHandle);
    json_response(['error' => 'Datei konnte nicht gesperrt werden'], 500);
    exit;
}

$existingContent = stream_get_contents($fileHandle);
$timestamp = date('Ymd_His');
$backupPath = $backupDir . "/events_2026_{$timestamp}.json";
file_put_contents($backupPath, $existingContent !== false ? $existingContent : '[]');

$encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($encoded === false) {
    flock($fileHandle, LOCK_UN);
    fclose($fileHandle);
    json_response(['error' => 'JSON Kodierung fehlgeschlagen'], 400);
    exit;
}

rewind($fileHandle);
ftruncate($fileHandle, 0);
fwrite($fileHandle, $encoded);
fflush($fileHandle);
flock($fileHandle, LOCK_UN);
fclose($fileHandle);

json_response(['ok' => true]);
