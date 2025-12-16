<?php
declare(strict_types=1);

if (!defined('APP_BASE')) {
    define('APP_BASE', realpath(__DIR__ . '/..') ?: (__DIR__ . '/..'));
}

header('Content-Type: application/json');

echo json_encode([
    'ok' => true,
    'php' => PHP_VERSION,
]);
