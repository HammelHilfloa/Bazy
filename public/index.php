<?php
$config = require __DIR__ . '/lib/bootstrap.php';

$baseUrl = rtrim($config['base_url'] ?? '', '/');
$target = $baseUrl . '/app/calendar.php';
header('Location: ' . $target, true, 302);
exit;
