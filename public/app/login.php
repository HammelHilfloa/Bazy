<?php
$config = require __DIR__ . '/../lib/bootstrap.php';

require_once __DIR__ . '/../lib/Csrf.php';
require_once __DIR__ . '/../lib/Auth.php';

$csrfToken = Csrf::getToken();
$baseUrl = rtrim($config['base_url'] ?? '', '/');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?php echo htmlspecialchars($config['app_name'] ?? 'Vereinskalender', ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7f7f7;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .card {
            background: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 420px;
        }
        h1 {
            margin-top: 0;
            font-size: 1.5rem;
            text-align: center;
        }
        label {
            display: block;
            margin: 1rem 0 0.25rem;
            font-weight: bold;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            margin-top: 1.5rem;
            padding: 0.85rem;
            border: none;
            border-radius: 6px;
            background: #1e88e5;
            color: #fff;
            font-size: 1rem;
            cursor: pointer;
        }
        button:hover {
            background: #1565c0;
        }
        .message {
            margin-top: 1rem;
            font-size: 0.95rem;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1><?php echo htmlspecialchars($config['app_name'] ?? 'Vereinskalender', ENT_QUOTES, 'UTF-8'); ?></h1>
        <form method="POST" action="<?php echo htmlspecialchars($baseUrl . '/api/auth/login.php', ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <label for="username">Benutzername</label>
            <input type="text" id="username" name="username" autocomplete="username" required>

            <label for="password">Passwort</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>

            <button type="submit">Anmelden</button>
        </form>
        <p class="message">Bitte melde dich an, um den Vereinskalender zu nutzen.</p>
    </div>
</body>
</html>
