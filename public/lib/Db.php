<?php
class Db
{
    private static ?PDO $pdo = null;

    public static function getConnection(array $config): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        if (!isset($config['db'])) {
            throw new InvalidArgumentException('Datenbankkonfiguration fehlt.');
        }

        $db = $config['db'];
        $host = $db['host'] ?? 'localhost';
        $port = $db['port'] ?? 3306;
        $name = $db['name'] ?? '';
        $charset = $db['charset'] ?? 'utf8mb4';
        $user = $db['user'] ?? '';
        $password = $db['password'] ?? '';

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        self::$pdo = new PDO($dsn, $user, $password, $options);
        return self::$pdo;
    }
}
