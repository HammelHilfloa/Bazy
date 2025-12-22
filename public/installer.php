<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/Installer.php';
require_once __DIR__ . '/lib/AuditLog.php';

$configPath = __DIR__ . '/config/config.php';
$configExamplePath = __DIR__ . '/config/config.example.php';
$schemaPath = __DIR__ . '/database/schema.sql';
$logFile = __DIR__ . '/logs/app.log';

$installerForce = false;
if (file_exists($configPath)) {
    $configFromFile = require $configPath;
    $installerForce = defined('INSTALLER_FORCE') && INSTALLER_FORCE === true;
    if (!$installerForce) {
        http_response_code(403);
        echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><title>Installer deaktiviert</title>';
        echo '<style>body{font-family:Arial,sans-serif;background:#f4f4f4;padding:40px;}';
        echo '.box{background:#fff;border:1px solid #ddd;border-radius:8px;padding:24px;max-width:640px;margin:0 auto;}</style></head><body>';
        echo '<div class="box"><h1>Installer deaktiviert</h1>';
        echo '<p>Die Datei <code>config/config.php</code> existiert bereits. Aus Sicherheitsgründen ist der Installer gesperrt.</p>';
        echo '<p>Falls du den Installer erneut ausführen möchtest, setze <code>INSTALLER_FORCE=true</code> in der Konfiguration oder lösche <code>config/config.php</code> manuell.</p>';
        echo '<p>Entferne <code>installer.php</code> vom Server, wenn die Installation abgeschlossen ist.</p>';
        echo '</div></body></html>';
        exit;
    }
}

Installer::startSession();
$csrfToken = Installer::getCsrfToken();

/**
 * @param array<string,string> $errors
 */
function fieldError(array $errors, string $key): ?string
{
    return $errors[$key] ?? null;
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$defaultBaseUrl = Installer::detectBaseUrl();

$formData = [
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => 'vereinskalender',
    'db_user' => '',
    'db_password' => '',
    'db_prefix' => '',
    'admin_username' => 'admin',
    'admin_password' => '',
    'admin_password_confirm' => '',
    'create_editor' => false,
    'editor_username' => 'editor',
    'editor_password' => '',
    'editor_password_confirm' => '',
    'app_name' => 'Vereinskalender',
    'base_url' => $defaultBaseUrl,
    'cron_token' => '',
    'confirm_reinstall' => false,
];

$errors = [];
$messages = [];
$progress = [];
$success = false;
$existingTables = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $formData['db_host'] = trim((string) ($_POST['db_host'] ?? $formData['db_host']));
    $formData['db_port'] = trim((string) ($_POST['db_port'] ?? $formData['db_port']));
    $formData['db_name'] = trim((string) ($_POST['db_name'] ?? $formData['db_name']));
    $formData['db_user'] = trim((string) ($_POST['db_user'] ?? $formData['db_user']));
    $formData['db_password'] = (string) ($_POST['db_password'] ?? $formData['db_password']);
    $formData['db_prefix'] = trim((string) ($_POST['db_prefix'] ?? $formData['db_prefix']));
    $formData['admin_username'] = trim((string) ($_POST['admin_username'] ?? $formData['admin_username']));
    $formData['admin_password'] = (string) ($_POST['admin_password'] ?? $formData['admin_password']);
    $formData['admin_password_confirm'] = (string) ($_POST['admin_password_confirm'] ?? $formData['admin_password_confirm']);
    $formData['create_editor'] = isset($_POST['create_editor']);
    $formData['editor_username'] = trim((string) ($_POST['editor_username'] ?? $formData['editor_username']));
    $formData['editor_password'] = (string) ($_POST['editor_password'] ?? $formData['editor_password']);
    $formData['editor_password_confirm'] = (string) ($_POST['editor_password_confirm'] ?? $formData['editor_password_confirm']);
    $formData['app_name'] = trim((string) ($_POST['app_name'] ?? $formData['app_name']));
    $formData['base_url'] = trim((string) ($_POST['base_url'] ?? $formData['base_url']));
    $formData['cron_token'] = trim((string) ($_POST['cron_token'] ?? $formData['cron_token']));
    $formData['confirm_reinstall'] = isset($_POST['confirm_reinstall']);

    if (!Installer::validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['global'] = 'Ungültiges CSRF-Token.';
    } else {
        $requirements = Installer::checkRequirements(__DIR__ . '/config', $logFile);
        if ($requirements['errors']) {
            $errors['requirements'] = implode(' ', $requirements['errors']);
        }

        if ($formData['db_host'] === '') {
            $errors['db_host'] = 'Host darf nicht leer sein.';
        }
        if ($formData['db_port'] === '' || !ctype_digit($formData['db_port'])) {
            $errors['db_port'] = 'Port muss eine Zahl sein.';
        }
        if ($formData['db_name'] === '') {
            $errors['db_name'] = 'Name der Datenbank wird benötigt.';
        }
        if ($formData['db_user'] === '') {
            $errors['db_user'] = 'Benutzername wird benötigt.';
        }
        if ($formData['db_prefix'] !== '' && !preg_match('/^[a-zA-Z0-9_]+$/', $formData['db_prefix'])) {
            $errors['db_prefix'] = 'Tabellen-Präfix darf nur Buchstaben, Ziffern und Unterstrich enthalten.';
        }
        if ($formData['admin_username'] === '') {
            $errors['admin_username'] = 'Admin-Benutzername darf nicht leer sein.';
        }
        if ($formData['admin_password'] === '') {
            $errors['admin_password'] = 'Admin-Passwort darf nicht leer sein.';
        } elseif (strlen($formData['admin_password']) < 8) {
            $errors['admin_password'] = 'Das Passwort sollte mindestens 8 Zeichen lang sein.';
        }
        if ($formData['admin_password'] !== $formData['admin_password_confirm']) {
            $errors['admin_password_confirm'] = 'Passwörter stimmen nicht überein.';
        }
        if ($formData['create_editor']) {
            if ($formData['editor_username'] === '') {
                $errors['editor_username'] = 'Editor-Benutzername darf nicht leer sein.';
            }
            if ($formData['editor_password'] === '') {
                $errors['editor_password'] = 'Editor-Passwort darf nicht leer sein.';
            } elseif (strlen($formData['editor_password']) < 8) {
                $errors['editor_password'] = 'Das Passwort sollte mindestens 8 Zeichen lang sein.';
            }
            if ($formData['editor_password'] !== $formData['editor_password_confirm']) {
                $errors['editor_password_confirm'] = 'Passwörter stimmen nicht überein.';
            }
        }
        if ($formData['app_name'] === '') {
            $errors['app_name'] = 'App-Name darf nicht leer sein.';
        }
        if ($formData['base_url'] === '' || !filter_var($formData['base_url'], FILTER_VALIDATE_URL)) {
            $errors['base_url'] = 'Base URL ist ungültig.';
        }
        if ($formData['cron_token'] !== '' && strlen($formData['cron_token']) < 12) {
            $errors['cron_token'] = 'Cron-Token sollte mindestens 12 Zeichen lang sein oder leer bleiben.';
        }

        $dbConfig = [
            'host' => $formData['db_host'],
            'port' => (int) $formData['db_port'],
            'name' => $formData['db_name'],
            'user' => $formData['db_user'],
            'password' => $formData['db_password'],
            'charset' => 'utf8mb4',
        ];

        if ($action === 'test_db' && !$errors) {
            try {
                $pdo = Installer::connect($dbConfig);
                $pdo = null;
                $messages[] = 'Datenbankverbindung erfolgreich getestet.';
            } catch (Throwable $e) {
                $errors['db_test'] = 'Datenbankverbindung fehlgeschlagen: ' . $e->getMessage();
            }
        }

        if ($action === 'delete_installer' && !$errors) {
            if (!is_writable(__FILE__)) {
                $errors['delete'] = 'Installer-Datei ist nicht löschbar. Bitte manuell per FTP/SCP entfernen.';
            } elseif (!@unlink(__FILE__)) {
                $errors['delete'] = 'Installer konnte nicht gelöscht werden. Bitte manuell entfernen.';
            } else {
                $messages[] = 'Installer-Datei wurde gelöscht.';
            }
        }

        if ($action === 'install' && !$errors) {
            try {
                $pdo = Installer::connect($dbConfig);
                $existingTables = Installer::existingTables($pdo, $formData['db_prefix']);

                if (!empty($existingTables) && !$formData['confirm_reinstall']) {
                    $errors['existing'] = 'Es wurden bereits Tabellen gefunden. Bestätige "Neu installieren (Drop & Create)" oder breche ab.';
                } else {
                    if (!empty($existingTables) && $formData['confirm_reinstall']) {
                        $progress[] = 'Bestehende Tabellen werden gelöscht…';
                        Installer::dropTables($pdo, $formData['db_prefix']);
                    }

                    $progress[] = 'Schema wird eingespielt…';
                    Installer::importSchema($pdo, $schemaPath, $formData['db_prefix']);

                    $pdo->beginTransaction();
                    $progress[] = 'Kategorien werden angelegt…';
                    Installer::seedCategories($pdo, $formData['db_prefix']);

                    $progress[] = 'Admin wird erstellt…';
                    $adminId = Installer::createUser($pdo, $formData['db_prefix'], $formData['admin_username'], $formData['admin_password'], 'admin');

                    $editorId = null;
                    if ($formData['create_editor']) {
                        $progress[] = 'Editor wird erstellt…';
                        $editorId = Installer::createUser($pdo, $formData['db_prefix'], $formData['editor_username'], $formData['editor_password'], 'editor');
                    }

                    $progress[] = 'Audit-Log wird geschrieben…';
                    AuditLog::record(
                        $pdo,
                        'import',
                        'installer',
                        'create',
                        $adminId,
                        null,
                        [
                            'admin' => $formData['admin_username'],
                            'editor_created' => $formData['create_editor'] ? $formData['editor_username'] : null,
                            'table_prefix' => $formData['db_prefix'],
                        ]
                    );

                    $pdo->commit();

                    $progress[] = 'Config wird geschrieben…';
                    $cronToken = $formData['cron_token'] !== '' ? $formData['cron_token'] : bin2hex(random_bytes(16));
                    Installer::writeConfig(
                        [
                            'db_host' => $formData['db_host'],
                            'db_port' => (int) $formData['db_port'],
                            'db_name' => $formData['db_name'],
                            'db_user' => $formData['db_user'],
                            'db_password' => $formData['db_password'],
                            'app_name' => $formData['app_name'],
                            'base_url' => rtrim($formData['base_url'], '/'),
                            'cron_token' => $cronToken,
                        ],
                        $configExamplePath,
                        $configPath
                    );

                    $success = true;
                    $_SESSION['installer_success'] = true;
                    $messages[] = 'Installation erfolgreich abgeschlossen.';
                }
            } catch (Throwable $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors['install'] = 'Installation fehlgeschlagen: ' . $e->getMessage();
            }
        }
    }
}

?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vereinskalender Installer</title>
    <style>
        :root { color-scheme: light; }
        body { font-family: Arial, sans-serif; background: #f6f7fb; margin: 0; padding: 0; }
        .container { max-width: 920px; margin: 32px auto; background: #fff; border: 1px solid #e2e2e2; border-radius: 12px; padding: 24px 28px 32px; box-shadow: 0 6px 18px rgba(0,0,0,0.05); }
        h1 { margin-top: 0; }
        h2 { border-bottom: 1px solid #eee; padding-bottom: 8px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(260px,1fr)); gap: 16px; }
        label { display: block; font-weight: 600; margin-bottom: 6px; }
        input[type="text"], input[type="number"], input[type="password"], input[type="url"] { width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        .section { margin-bottom: 24px; padding: 16px; border: 1px solid #eee; border-radius: 10px; background: #fafbff; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 16px; }
        button { border: none; border-radius: 6px; padding: 12px 16px; font-size: 15px; cursor: pointer; }
        .primary { background: #1e88e5; color: #fff; }
        .secondary { background: #f0f0f0; color: #333; }
        .danger { background: #e53935; color: #fff; }
        .success { background: #e8f5e9; border: 1px solid #c8e6c9; color: #256029; padding: 12px; border-radius: 8px; margin-bottom: 12px; }
        .error { background: #ffebee; border: 1px solid #ffcdd2; color: #b71c1c; padding: 12px; border-radius: 8px; margin-bottom: 12px; }
        .warning { background: #fff8e1; border: 1px solid #ffe082; color: #8d6e0d; padding: 12px; border-radius: 8px; margin-bottom: 12px; }
        .muted { color: #666; font-size: 14px; }
        .inline { display: inline-flex; align-items: center; gap: 8px; }
        ul.clean { padding-left: 20px; margin: 8px 0; }
        .progress { padding: 12px; background: #f4f8ff; border: 1px solid #dce5ff; border-radius: 8px; }
        .badge { display: inline-block; background: #1e88e5; color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 12px; margin-right: 6px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Vereinskalender Installer</h1>
        <p class="muted">Schrittweiser Assistent zur Einrichtung. Nach erfolgreicher Installation bitte <code>installer.php</code> entfernen.</p>

        <?php if ($messages): ?>
            <?php foreach ($messages as $message): ?>
                <div class="success"><?= esc($message) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($errors): ?>
            <?php foreach ($errors as $message): ?>
                <div class="error"><?= esc($message) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="post" action="installer.php">
            <input type="hidden" name="csrf_token" value="<?= esc($csrfToken) ?>">
            <div class="section">
                <h2>Schritt A: Datenbank</h2>
                <div class="grid">
                    <div>
                        <label for="db_host">Host</label>
                        <input type="text" id="db_host" name="db_host" value="<?= esc($formData['db_host']) ?>" required>
                        <?php if ($error = fieldError($errors, 'db_host')): ?><div class="error"><?= esc($error) ?></div><?php endif; ?>
                    </div>
                    <div>
                        <label for="db_port">Port</label>
                        <input type="number" id="db_port" name="db_port" value="<?= esc($formData['db_port']) ?>" required>
                        <?php if ($error = fieldError($errors, 'db_port')): ?><div class="error"><?= esc($error) ?></div><?php endif; ?>
                    </div>
                    <div>
                        <label for="db_name">Datenbankname</label>
                        <input type="text" id="db_name" name="db_name" value="<?= esc($formData['db_name']) ?>" required>
                        <?php if ($error = fieldError($errors, 'db_name')): ?><div class="error"><?= esc($error) ?></div><?php endif; ?>
                    </div>
                    <div>
                        <label for="db_user">DB-User</label>
                        <input type="text" id="db_user" name="db_user" value="<?= esc($formData['db_user']) ?>" required>
                        <?php if ($error = fieldError($errors, 'db_user')): ?><div class="error"><?= esc($error) ?></div><?php endif; ?>
                    </div>
                    <div>
                        <label for="db_password">DB-Passwort</label>
                        <input type="password" id="db_password" name="db_password" value="<?= esc($formData['db_password']) ?>">
                        <?php if ($error = fieldError($errors, 'db_password')): ?><div class="error"><?= esc($error) ?></div><?php endif; ?>
                    </div>
                    <div>
                        <label for="db_prefix">Tabellen-Präfix (optional)</label>
                        <input type="text" id="db_prefix" name="db_prefix" value="<?= esc($formData['db_prefix']) ?>" placeholder="z. B. vk_">
                        <?php if ($error = fieldError($errors, 'db_prefix')): ?><div class="error"><?= esc($error) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="actions">
                    <button class="secondary" type="submit" name="action" value="test_db">Test DB Verbindung</button>
                </div>
            </div>

            <div class="section">
                <h2>Schritt B: Admin-Account</h2>
                <div class="grid">
                    <div>
                        <label for="admin_username">Admin Benutzername</label>
                        <input type="text" id="admin_username" name="admin_username" value="<?= esc($formData['admin_username']) ?>" required>
                        <?php if ($error = fieldError($errors, 'admin_username')): ?><div class="error"><?= esc($error) ?></div><?php endif; ?>
                    </div>
                    <div>
                        <label for="admin_password">Admin Passwort</label>
                        <input type="password" id="admin_password" name="admin_password" value="<?= esc($formData['admin_password']) ?>" required>
                        <?php if ($error = fieldError($errors, 'admin_password')): ?><div class="error"><?= esc($error) ?></div><?php endif; ?>
                    </div>
                    <div>
                        <label for="admin_password_confirm">Passwort bestätigen</label>
                        <input type="password" id="admin_password_confirm" name="admin_password_confirm" value="<?= esc($formData['admin_password_confirm']) ?>" required>
                        <?php if ($error = fieldError($errors, 'admin_password_confirm')): ?><div class="error"><?= esc($error) ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="section" style="margin-top:16px;">
                    <div class="inline">
                        <input type="checkbox" id="create_editor" name="create_editor" <?= $formData['create_editor'] ? 'checked' : '' ?>>
                        <label for="create_editor" style="margin:0;">Zusätzlichen Editor-User anlegen</label>
                    </div>
                    <div class="grid" style="margin-top:12px;">
                        <div>
                            <label for="editor_username">Editor Benutzername</label>
                            <input type="text" id="editor_username" name="editor_username" value="<?= esc($formData['editor_username']) ?>">
                            <?php if ($error = fieldError($errors, 'editor_username')): ?><div class="error"><?= esc($error) ?></div><?php endif; ?>
                        </div>
                        <div>
                            <label for="editor_password">Editor Passwort</label>
                            <input type="password" id="editor_password" name="editor_password" value="<?= esc($formData['editor_password']) ?>">
                            <?php if ($error = fieldError($errors, 'editor_password')): ?><div class="error"><?= esc($error) ?></div><?php endif; ?>
                        </div>
                        <div>
                            <label for="editor_password_confirm">Passwort bestätigen</label>
                            <input type="password" id="editor_password_confirm" name="editor_password_confirm" value="<?= esc($formData['editor_password_confirm']) ?>">
                            <?php if ($error = fieldError($errors, 'editor_password_confirm')): ?><div class="error"><?= esc($error) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <p class="muted">Editor wird nur angelegt, wenn die Checkbox aktiviert ist.</p>
                </div>
            </div>

            <div class="section">
                <h2>Schritt C: Zusammenfassung & Einstellungen</h2>
                <div class="grid">
                    <div>
                        <label for="app_name">App-Name</label>
                        <input type="text" id="app_name" name="app_name" value="<?= esc($formData['app_name']) ?>" required>
                        <?php if ($error = fieldError($errors, 'app_name')): ?><div class="error"><?= esc($error) ?></div><?php endif; ?>
                    </div>
                    <div>
                        <label for="base_url">Base URL</label>
                        <input type="url" id="base_url" name="base_url" value="<?= esc($formData['base_url']) ?>" required>
                        <?php if ($error = fieldError($errors, 'base_url')): ?><div class="error"><?= esc($error) ?></div><?php endif; ?>
                        <p class="muted">Vorschlag: <?= esc($defaultBaseUrl) ?></p>
                    </div>
                    <div>
                        <label for="cron_token">Cron-Token (optional, leer = zufällig)</label>
                        <input type="text" id="cron_token" name="cron_token" value="<?= esc($formData['cron_token']) ?>" placeholder="leer lassen für Zufallswert">
                        <?php if ($error = fieldError($errors, 'cron_token')): ?><div class="error"><?= esc($error) ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="warning" style="margin-top:12px;">
                    <div><strong>Prüfungen vor dem Installieren:</strong></div>
                    <ul class="clean">
                        <li>PHP-Version &amp; Erweiterungen (PDO, pdo_mysql)</li>
                        <li>Schreibrechte auf <code>/config/</code> und <code>/logs/</code></li>
                        <li>Schema aus <code>database/schema.sql</code> wird eingespielt</li>
                        <li>Kategorien und Benutzer werden angelegt</li>
                    </ul>
                </div>

                <?php if (!empty($existingTables)): ?>
                    <div class="warning">
                        <div><strong>Warnung:</strong> Bereits vorhandene Tabellen werden erkannt.</div>
                        <ul class="clean">
                            <?php foreach ($existingTables as $table): ?>
                                <li><?= esc($table) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="inline" style="margin-top:8px;">
                            <input type="checkbox" id="confirm_reinstall" name="confirm_reinstall" <?= $formData['confirm_reinstall'] ? 'checked' : '' ?>>
                            <label for="confirm_reinstall" style="margin:0;">Neu installieren (Drop &amp; Create)</label>
                        </div>
                        <p class="muted">Ohne Bestätigung wird abgebrochen.</p>
                    </div>
                <?php endif; ?>

                <?php if ($progress): ?>
                    <div class="progress">
                        <?php foreach ($progress as $step): ?>
                            <div><span class="badge">•</span><?= esc($step) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="actions">
                    <button class="primary" type="submit" name="action" value="install">Installieren</button>
                    <button class="secondary" type="reset">Zurücksetzen</button>
                </div>
            </div>
        </form>

        <?php if ($success): ?>
            <div class="section">
                <h2>Installation erfolgreich</h2>
                <p>Die Anwendung wurde eingerichtet. Bitte entferne den Installer, um unbefugte Zugriffe zu verhindern.</p>
                <form method="post" action="installer.php">
                    <input type="hidden" name="csrf_token" value="<?= esc($csrfToken) ?>">
                    <button class="danger" type="submit" name="action" value="delete_installer">Installer löschen</button>
                </form>
                <p class="muted">Falls das automatische Löschen scheitert: <code>rm public/installer.php</code> oder per FTP entfernen.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
