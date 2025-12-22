<?php

class Installer
{
    public const MIN_PHP_VERSION = '8.1';
    private const REQUIRED_EXTENSIONS = ['pdo', 'pdo_mysql'];
    private const TABLES = ['users', 'categories', 'events', 'event_series', 'event_overrides', 'audit_log'];
    private const DROP_ORDER = ['event_overrides', 'event_series', 'events', 'categories', 'audit_log', 'users'];

    /**
     * @var array<int,array{name:string,color:string,sort_order:int}>
     */
    private const CATEGORY_SEED = [
        ['name' => 'Mitgliederversammlung', 'color' => '#1E88E5', 'sort_order' => 10],
        ['name' => 'Training', 'color' => '#43A047', 'sort_order' => 20],
        ['name' => 'Spiel/Match', 'color' => '#E53935', 'sort_order' => 30],
        ['name' => 'Turnier', 'color' => '#8E24AA', 'sort_order' => 40],
        ['name' => 'Feier/Events', 'color' => '#FB8C00', 'sort_order' => 50],
    ];

    private const CSRF_KEY = 'installer_csrf';

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        session_regenerate_id(true);
    }

    public static function getCsrfToken(): string
    {
        self::startSession();

        if (empty($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CSRF_KEY];
    }

    public static function validateCsrfToken(?string $token): bool
    {
        self::startSession();
        $stored = $_SESSION[self::CSRF_KEY] ?? null;

        return is_string($token) && is_string($stored) && hash_equals($stored, $token);
    }

    /**
     * @return array{errors: string[], warnings: string[]}
     */
    public static function checkRequirements(string $configDir, string $logFile): array
    {
        $errors = [];
        $warnings = [];

        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
            $errors[] = sprintf('PHP %s oder höher wird benötigt (gefunden: %s).', self::MIN_PHP_VERSION, PHP_VERSION);
        }

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            if (!extension_loaded($extension)) {
                $errors[] = sprintf('Die PHP-Erweiterung "%s" muss aktiviert sein.', $extension);
            }
        }

        if (!is_dir($configDir) || !is_writable($configDir)) {
            $errors[] = 'Das Verzeichnis /config/ ist nicht beschreibbar.';
        }

        $logDir = dirname($logFile);
        if (!is_dir($logDir) && !@mkdir($logDir, 0775, true)) {
            $errors[] = 'Das Log-Verzeichnis /logs/ kann nicht erstellt werden.';
        } elseif (!is_writable($logDir)) {
            $errors[] = 'Das Log-Verzeichnis /logs/ ist nicht beschreibbar.';
        } else {
            $handle = @fopen($logFile, 'ab');
            if ($handle === false) {
                $errors[] = 'Die Datei logs/app.log kann nicht beschrieben werden.';
            } else {
                fclose($handle);
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * @param array{host:string,port:int,name:string,user:string,password:string,charset?:string} $db
     */
    public static function connect(array $db): PDO
    {
        $host = $db['host'];
        $port = $db['port'];
        $name = $db['name'];
        $charset = $db['charset'] ?? 'utf8mb4';
        $user = $db['user'];
        $password = $db['password'];

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        return new PDO($dsn, $user, $password, $options);
    }

    /**
     * @return array<int,string>
     */
    public static function existingTables(PDO $pdo, string $prefix): array
    {
        $tables = [];
        foreach (self::TABLES as $name) {
            $tableName = $prefix . $name;
            $stmt = $pdo->prepare('SHOW TABLES LIKE :name');
            $stmt->execute(['name' => $tableName]);
            if ($stmt->fetchColumn()) {
                $tables[] = $tableName;
            }
        }

        return $tables;
    }

    public static function dropTables(PDO $pdo, string $prefix): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (self::DROP_ORDER as $name) {
            $table = sprintf('`%s%s`', $prefix, $name);
            $pdo->exec('DROP TABLE IF EXISTS ' . $table);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    public static function importSchema(PDO $pdo, string $schemaPath, string $prefix): void
    {
        $sql = @file_get_contents($schemaPath);
        if ($sql === false) {
            throw new RuntimeException('Schema-Datei konnte nicht gelesen werden.');
        }

        $statements = self::splitSqlStatements(self::applyPrefix($sql, $prefix));
        if (empty($statements)) {
            throw new RuntimeException('Keine SQL-Statements im Schema gefunden.');
        }

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }

    public static function seedCategories(PDO $pdo, string $prefix): void
    {
        $sql = sprintf(
            'INSERT INTO `%1$scategories` (`name`, `color`, `sort_order`, `is_active`, `created_at`)
             VALUES (:name, :color, :sort_order, 1, NOW())
             ON DUPLICATE KEY UPDATE `color` = VALUES(`color`), `sort_order` = VALUES(`sort_order`), `is_active` = VALUES(`is_active`)',
            $prefix
        );

        $stmt = $pdo->prepare($sql);
        foreach (self::CATEGORY_SEED as $category) {
            $stmt->execute([
                'name' => $category['name'],
                'color' => $category['color'],
                'sort_order' => $category['sort_order'],
            ]);
        }
    }

    public static function createUser(PDO $pdo, string $prefix, string $username, string $password, string $role = 'admin'): int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = sprintf(
            'INSERT INTO `%1$susers` (`username`, `password_hash`, `role`, `is_active`, `created_at`)
             VALUES (:username, :password_hash, :role, 1, NOW())',
            $prefix
        );

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'username' => $username,
            'password_hash' => $hash,
            'role' => $role,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function writeConfig(array $configValues, string $examplePath, string $targetPath): void
    {
        $template = @require $examplePath;
        if (!is_array($template)) {
            $template = [];
        }

        $template['db'] = [
            'host' => $configValues['db_host'],
            'port' => $configValues['db_port'],
            'name' => $configValues['db_name'],
            'user' => $configValues['db_user'],
            'password' => $configValues['db_password'],
            'charset' => 'utf8mb4',
        ];
        $template['timezone'] = 'Europe/Berlin';
        $template['base_url'] = $configValues['base_url'];
        $template['app_name'] = $configValues['app_name'];
        $template['cron_token'] = $configValues['cron_token'] ?: null;

        $content = "<?php\n";
        $content .= "if (!defined('INSTALLER_FORCE')) {\n    define('INSTALLER_FORCE', false);\n}\n\n";
        $content .= 'return ' . self::exportConfig($template) . ";\n";

        if (@file_put_contents($targetPath, $content, LOCK_EX) === false) {
            throw new RuntimeException('config.php konnte nicht geschrieben werden.');
        }
    }

    public static function detectBaseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        $dir = $dir === '/' ? '' : $dir;

        return rtrim($scheme . '://' . $host . $dir, '/');
    }

    /**
     * @return array<int,string>
     */
    private static function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inString = false;
        $stringChar = '';
        $inLineComment = false;
        $inBlockComment = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                }
                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }
                continue;
            }

            if (!$inString) {
                if ($char === '-' && $next === '-') {
                    $inLineComment = true;
                    $i++;
                    continue;
                }
                if ($char === '/' && $next === '*') {
                    $inBlockComment = true;
                    $i++;
                    continue;
                }
                if ($char === '#') {
                    $inLineComment = true;
                    continue;
                }
                if ($char === '\'' || $char === '"') {
                    $inString = true;
                    $stringChar = $char;
                    $buffer .= $char;
                    continue;
                }
                if ($char === ';') {
                    $trimmed = trim($buffer);
                    if ($trimmed !== '') {
                        $statements[] = $trimmed;
                    }
                    $buffer = '';
                    continue;
                }
            } else {
                if ($char === $stringChar && self::isStringDelimiter($sql, $i)) {
                    $inString = false;
                    $stringChar = '';
                }
            }

            $buffer .= $char;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }

    private static function isStringDelimiter(string $sql, int $position): bool
    {
        $escapeCount = 0;
        $index = $position - 1;
        while ($index >= 0 && $sql[$index] === '\\') {
            $escapeCount++;
            $index--;
        }

        return ($escapeCount % 2) === 0;
    }

    private static function applyPrefix(string $sql, string $prefix): string
    {
        if ($prefix === '') {
            return $sql;
        }

        foreach (self::TABLES as $table) {
            $sql = str_replace(sprintf('`%s`', $table), sprintf('`%s%s`', $prefix, $table), $sql);
        }

        return $sql;
    }

    /**
     * @param array<string,mixed> $config
     */
    private static function exportConfig(array $config): string
    {
        $export = var_export($config, true);
        return $export;
    }
}
