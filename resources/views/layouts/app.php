<?php use App\Support\View; ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Coaching Plattform', ENT_QUOTES) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="app-body">
<nav class="top-nav" aria-label="Hauptnavigation">
    <a class="brand" href="<?= !empty($_SESSION['user_id']) ? '/calendar' : '/' ?>">Coaching Plattform</a>
    <ul>
        <?php if (!empty($_SESSION['user_id'])): ?>
            <li><a href="/calendar">Kalender</a></li>
            <li><a href="/offers">Angebote</a></li>
            <li><a href="/invoices">Rechnungen</a></li>
        <?php endif; ?>
    </ul>
    <ul>
        <?php if (!empty($_SESSION['user_id'])): ?>
            <li>
                <form method="POST" action="/logout">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars(App\Http\Middleware\CsrfMiddleware::token(), ENT_QUOTES) ?>">
                    <button type="submit" class="contrast">Logout (<?= htmlspecialchars($_SESSION['user_name'] ?? 'Coach', ENT_QUOTES) ?>)</button>
                </form>
            </li>
        <?php else: ?>
            <li><a href="/login">Login</a></li>
        <?php endif; ?>
    </ul>
</nav>
<main class="app-main">
    <?= View::renderPartial($viewTemplate, get_defined_vars()) ?>
</main>
</body>
</html>
