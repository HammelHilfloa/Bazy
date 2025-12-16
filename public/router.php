<?php
declare(strict_types=1);

define('APP_BASE', realpath(__DIR__ . '/..') ?: (__DIR__ . '/..'));

function send_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path = rtrim($uri, '/');
if ($path === '') {
    $path = '/';
}

if (strpos($path, '/api/') === 0) {
    $aliases = [
        '/api/events' => '/api/events.php',
        '/api/holidays' => '/api/holidays.php',
        '/api/csrf' => '/api/csrf.php',
        '/api/health' => '/api/health.php',
    ];

    if (isset($aliases[$path])) {
        $path = $aliases[$path];
    }

    $whitelist = [
        '/api/events.php' => APP_BASE . '/api/events.php',
        '/api/holidays.php' => APP_BASE . '/api/holidays.php',
        '/api/csrf.php' => APP_BASE . '/api/csrf.php',
        '/api/health.php' => APP_BASE . '/api/health.php',
    ];

    if (!isset($whitelist[$path])) {
        send_json(['error' => 'not_found'], 404);
        exit;
    }

    $target = $whitelist[$path];
    if (!is_file($target)) {
        send_json(['error' => 'not_found'], 404);
        exit;
    }

    require $target;
    exit;
}

$frontend = __DIR__ . '/index.html';
if (is_file($frontend)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($frontend);
    exit;
}

http_response_code(404);
echo 'Not Found';
