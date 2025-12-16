<?php
declare(strict_types=1);

// Projekt-Root (Ordner über /public)
define('APP_BASE', realpath(__DIR__ . '/..') ?: (__DIR__ . '/..'));

$uri  = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';
$path = rawurldecode($path);

// Normalisieren (kein doppelter Slash, kein trailing slash außer root)
$path = preg_replace('#/+#', '/', $path);
if ($path !== '/' && str_ends_with($path, '/')) $path = rtrim($path, '/');

// Whitelist-Routing (WICHTIG: nur diese Endpoints erlauben)
$routes = [
  '/api/events.php'   => APP_BASE . '/api/events.php',
  '/api/csrf.php'     => APP_BASE . '/api/csrf.php',
  '/api/holidays.php' => APP_BASE . '/api/holidays.php',

  // optional ohne .php
  '/api/events'   => APP_BASE . '/api/events.php',
  '/api/csrf'     => APP_BASE . '/api/csrf.php',
  '/api/holidays' => APP_BASE . '/api/holidays.php',
];

if (str_starts_with($path, '/api/')) {
  if (!isset($routes[$path])) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'error' => 'not_found',
      'path'  => $path,
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $target = $routes[$path];

  if (!is_file($target)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'error' => 'server_misconfig',
      'message' => 'Route mapped file not found on disk',
      'mapped' => $target,
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  require $target;
  exit;
}

// Alles andere: Frontend ausliefern
$indexHtml = __DIR__ . '/index.html';
if (is_file($indexHtml)) {
  header('Content-Type: text/html; charset=utf-8');
  readfile($indexHtml);
  exit;
}

http_response_code(404);
echo "index.html not found";
