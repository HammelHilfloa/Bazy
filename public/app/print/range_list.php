<?php
$config = require __DIR__ . '/../../lib/bootstrap.php';

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Db.php';
require_once __DIR__ . '/../../lib/EventListService.php';
require_once __DIR__ . '/../../lib/Util.php';
require_once __DIR__ . '/_print_helpers.php';

Auth::requireLogin();

$fromInput = trim($_GET['from'] ?? '');
$toInput = trim($_GET['to'] ?? '');

if ($fromInput === '' || $toInput === '') {
    http_response_code(400);
    echo 'Parameter "from" und "to" sind erforderlich.';
    exit;
}

$fromDate = Util::parseDateTime($fromInput . ' 00:00:00');
$toDate = Util::parseDateTime($toInput . ' 23:59:59');

if (!$fromDate || !$toDate || $fromDate > $toDate) {
    http_response_code(400);
    echo 'Datumsbereich ist ungültig.';
    exit;
}

$categoryIds = print_parse_category_ids(trim($_GET['category_ids'] ?? ''));
$includeSystem = (int) ($_GET['include_system'] ?? 0) === 1;

try {
    $pdo = Db::getConnection($config);
    $events = EventListService::fetchEvents($pdo, $fromDate, $toDate, $categoryIds, $includeSystem);
    $data = print_prepare_data($events, $pdo, $config);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Druckansicht konnte nicht geladen werden.';
    exit;
}

$baseUrl = rtrim($config['base_url'] ?? '', '/');
$appName = htmlspecialchars($config['app_name'] ?? 'Vereinskalender', ENT_QUOTES, 'UTF-8');
$rangeLabel = sprintf('Zeitraum %s – %s', $fromDate->format('d.m.Y'), $toDate->format('d.m.Y'));
$now = (new DateTimeImmutable('now'))->format('d.m.Y H:i');

?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Druckansicht Zeitraum | <?php echo $appName; ?></title>
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
