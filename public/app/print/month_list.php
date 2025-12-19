<?php
$config = require __DIR__ . '/../../lib/bootstrap.php';

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Db.php';
require_once __DIR__ . '/../../lib/EventListService.php';
require_once __DIR__ . '/../../lib/Util.php';
require_once __DIR__ . '/_print_helpers.php';

Auth::requireLogin();

$year = (int) ($_GET['year'] ?? 0);
$month = (int) ($_GET['month'] ?? 0);

if ($year < 1970 || $year > 2100 || $month < 1 || $month > 12) {
    http_response_code(400);
    echo 'Ungültige Parameter.';
    exit;
}

$categoryIds = print_parse_category_ids(trim($_GET['category_ids'] ?? ''));
$includeSystem = (int) ($_GET['include_system'] ?? 0) === 1;

$start = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
$end = $start->modify('last day of this month')->setTime(23, 59, 59);

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
$rangeLabel = sprintf('Monat %s.%s', $start->format('m'), $start->format('Y'));
$now = (new DateTimeImmutable('now'))->format('d.m.Y H:i');

?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Druckansicht Monat | <?php echo $appName; ?></title>
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
