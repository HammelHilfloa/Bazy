<?php
// Lightweight router for PHP built-in server
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = __DIR__;

// Serve existing files directly
$fullPath = realpath($root . $uri);
if ($fullPath && is_file($fullPath)) {
    return false;
}

if (str_starts_with($uri, '/api/')) {
    $apiScript = $root . $uri;
    if (file_exists($apiScript)) {
        require $apiScript;
        return true;
    }
}

// Default: deliver frontend
require $root . '/public/index.html';
return true;
