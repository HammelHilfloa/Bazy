<?php
$config = require __DIR__ . '/../../lib/bootstrap.php';

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Db.php';
require_once __DIR__ . '/../../lib/EventListService.php';
require_once __DIR__ . '/../../lib/Util.php';
require_once __DIR__ . '/_print_helpers.php';

Auth::requireLogin();

$dateInput = trim($_GET['date'] ?? '');
if ($dateInput === '') {
    http_response_code(400);
    echo 'Parameter "date" fehlt.';
    exit;
}

$date = Util::parseDateTime($dateInput . ' 00:00:00');
if (!$date) {
    http_response_code(400);
    echo 'Ungültiges Datum.';
    exit;
}

$categoryIds = print_parse_category_ids(trim($_GET['category_ids'] ?? ''));
$includeSystem = (int) ($_GET['include_system'] ?? 0) === 1;

$start = $date->modify('monday this week')->setTime(0, 0, 0);
$end = $start->modify('+6 days')->setTime(23, 59, 59);

try {
    $pdo = Db::getConnection($config);
    $events = EventListService::fetchEvents($pdo, $start, $end, $categoryIds, $includeSystem);
    $data = print_prepare_data($events, $pdo, $config);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Druckansicht konnte nicht geladen werden.';
    exit;
}

$baseUrl = rtrim($config['base_url'] ?? '', '/');
$appName = htmlspecialchars($config['app_name'] ?? 'Vereinskalender', ENT_QUOTES, 'UTF-8');
$rangeLabel = sprintf(
    'KW %s (%s – %s)',
    $start->format('W'),
    $start->format('d.m.Y'),
    $end->format('d.m.Y')
);
$now = (new DateTimeImmutable('now'))->format('d.m.Y H:i');

?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Druckansicht Woche | <?php echo $appName; ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl . '/assets/css/print.css', ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
    <div class="page">
        <header class="print-header">
            <h1><?php echo $appName; ?></h1>
            <div class="print-meta">
                <div><?php echo htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                <div>Stand: <?php echo htmlspecialchars($now, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </header>

        <?php echo print_render_sections($data['groupedEvents'], $data['categories']); ?>
    </div>
</body>
</html>
