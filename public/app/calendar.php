<?php
$config = require __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
echo ($config['app_name'] ?? 'Vereinskalender') . " API bereit. Zugriff bitte über die JSON-API mit gültigem Login.";
