<?php
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/ErrorHandler.php';

$configFile = __DIR__ . '/../config/config.php';
$exampleFile = __DIR__ . '/../config/config.example.php';

if (file_exists($configFile)) {
    $config = require $configFile;
} elseif (file_exists($exampleFile)) {
    $config = require $exampleFile;
} else {
    throw new RuntimeException('Keine Konfiguration gefunden.');
}

if (!is_array($config)) {
    throw new RuntimeException('Die Konfiguration muss ein Array zurückgeben.');
}

$timezone = $config['timezone'] ?? 'Europe/Berlin';
if (!@date_default_timezone_set($timezone)) {
    date_default_timezone_set('Europe/Berlin');
}

ErrorHandler::register();

return $config;
