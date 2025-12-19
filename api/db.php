<?php
/**
 * Simple PDO helper for shared hosting.
 */

/**
 * Resolve configuration values.
 * - Loads a .env file from the project root if present (key=value format).
 * - Prefers real environment variables over .env values.
 */
function env(string $key, $default = null)
{
    static $loaded = false;

    if (!$loaded) {
        $envPath = dirname(__DIR__) . '/.env';
        if (is_readable($envPath)) {
            $parsed = parse_ini_file($envPath, false, INI_SCANNER_RAW);
            if (is_array($parsed)) {
                foreach ($parsed as $name => $value) {
                    if (getenv($name) === false && !array_key_exists($name, $_ENV)) {
                        putenv("{$name}={$value}");
                        $_ENV[$name] = $value;
                    }
                }
            }
        }
        $loaded = true;
    }

    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }

    return $value;
}

function get_pdo(): PDO
{
    $charset = env('DB_CHARSET', 'utf8mb4');
    [$dsn, $user, $pass] = build_dsn($charset);

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    return new PDO($dsn, $user, $pass, $options);
}

function build_dsn(string $charset): array
{
    // Explicit DSN override (e.g. sqlite: or mysql:)
    $dsnOverride = env('DB_DSN');
    if (!empty($dsnOverride)) {
        return [$dsnOverride, env('DB_USER', ''), env('DB_PASS', '')];
    }

    // DATABASE_URL style (mysql://user:pass@host:3306/dbname)
    $databaseUrl = env('DATABASE_URL');
    if (!empty($databaseUrl)) {
        $parts = parse_url($databaseUrl);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host']) || empty($parts['path'])) {
            throw new RuntimeException('Ungültige DATABASE_URL Konfiguration');
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('DATABASE_URL unterstützt nur mysql/mariadb');
        }

        $db = ltrim($parts['path'], '/');
        $user = urldecode($parts['user'] ?? '');
        $pass = urldecode($parts['pass'] ?? '');
        $host = $parts['host'];
        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        $dsn = build_mysql_dsn($host, $db, $charset, $port);
        return [$dsn, $user, $pass];
    }

    // Default MySQL credentials (configurable via env / .env)
    $host = env('DB_HOST', '127.0.0.1');
    $db   = env('DB_NAME', 'bazy');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');
    $port = env('DB_PORT');

    if (!$host || !$db || !$user) {
        throw new RuntimeException('DB Verbindung ist nicht konfiguriert (DB_HOST/DB_NAME/DB_USER)');
    }

    $dsn = build_mysql_dsn($host, $db, $charset, $port ? (int) $port : null);

    return [$dsn, $user, $pass];
}

function build_mysql_dsn(string $host, string $db, string $charset, ?int $port = null): string
{
    $portPart = $port ? ";port={$port}" : '';
    return "mysql:host={$host}{$portPart};dbname={$db};charset={$charset}";
}

function get_setting(PDO $pdo, string $key): ?string
{
    $stmt = $pdo->prepare('SELECT value FROM holiday_sync WHERE `key` = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : null;
}

function set_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare('INSERT INTO holiday_sync (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    $stmt->execute([$key, $value]);
}
