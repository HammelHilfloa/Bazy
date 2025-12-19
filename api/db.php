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
    [$dsn, $user, $pass, $driver, $isExplicit] = build_dsn($charset);

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (Throwable $e) {
        // When no explicit database configuration was provided, fall back to a local SQLite file
        // so the application remains usable without MySQL/MariaDB.
        if ($driver === 'mysql' && $isExplicit === false) {
            error_log('MySQL Verbindung fehlgeschlagen, wechsle auf SQLite: ' . $e->getMessage());
            $pdo = create_sqlite_pdo();
        } else {
            throw $e;
        }
    }

    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        bootstrap_sqlite($pdo);
    }

    return $pdo;
}

function build_dsn(string $charset): array
{
    // Explicit DSN override (e.g. sqlite: or mysql:)
    $dsnOverride = env('DB_DSN');
    if (!empty($dsnOverride)) {
        return [$dsnOverride, env('DB_USER', ''), env('DB_PASS', ''), detect_driver_from_dsn($dsnOverride), true];
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
        return [$dsn, $user, $pass, $scheme, true];
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

    return [$dsn, $user, $pass, 'mysql', false];
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
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $stmt = $pdo->prepare('INSERT INTO holiday_sync (`key`, value) VALUES (?, ?) ON CONFLICT(`key`) DO UPDATE SET value = excluded.value');
        $stmt->execute([$key, $value]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO holiday_sync (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
        $stmt->execute([$key, $value]);
    }
}

function detect_driver_from_dsn(string $dsn): string
{
    $pos = strpos($dsn, ':');
    return $pos === false ? 'mysql' : strtolower(substr($dsn, 0, $pos));
}

function create_sqlite_pdo(): PDO
{
    $dbPath = dirname(__DIR__) . '/bazy.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

function bootstrap_sqlite(PDO $pdo): void
{
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec('CREATE TABLE IF NOT EXISTS `groups` (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(64) NOT NULL UNIQUE,
        created_at TEXT DEFAULT (datetime(\'now\')),
        updated_at TEXT DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        group_id INTEGER NOT NULL,
        title VARCHAR(200) NOT NULL,
        start_at DATETIME NOT NULL,
        end_at DATETIME NOT NULL,
        location VARCHAR(200),
        notes TEXT,
        created_at TEXT DEFAULT (datetime(\'now\')),
        updated_at TEXT DEFAULT (datetime(\'now\')),
        FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS holiday_entries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        source VARCHAR(32) NOT NULL,
        kind TEXT NOT NULL,
        name VARCHAR(255) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        region VARCHAR(16) NOT NULL DEFAULT \'DE-NW\',
        year SMALLINT NOT NULL,
        checksum CHAR(40) NULL,
        fetched_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    )');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_holiday_unique ON holiday_entries (region, year, kind, name, start_date, end_date)');

    $pdo->exec('CREATE TABLE IF NOT EXISTS holiday_sync (
        `key` VARCHAR(64) PRIMARY KEY,
        value TEXT
    )');

    $insertGroups = $pdo->prepare('INSERT OR IGNORE INTO `groups` (id, name) VALUES (:id, :name)');
    $defaults = [
        1 => 'U11',
        2 => 'U13',
        3 => 'U15',
        4 => 'U18',
        5 => 'U21',
        6 => 'Senioren',
        7 => 'JSL',
        8 => 'Jugend',
        9 => 'Große',
    ];
    foreach ($defaults as $id => $name) {
        $insertGroups->execute([':id' => $id, ':name' => $name]);
    }
}
