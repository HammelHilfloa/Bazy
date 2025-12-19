<?php
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Csrf.php';

class AuthException extends RuntimeException
{
    private int $status;

    public function __construct(string $message, int $status = 400, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->status = $status;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}

class Auth
{
    private const LOGIN_ATTEMPT_LIMIT = 5;
    private const LOGIN_ATTEMPT_WINDOW = 300; // 5 Minuten
    private const SESSION_USER_KEY = 'user';
    private const SESSION_RATE_KEY = 'login_rate';

    private static function startSession(): void
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
    }

    public static function login(PDO $pdo, string $username, string $password): array
    {
        self::startSession();
        self::enforceRateLimit();

        $stmt = $pdo->prepare('SELECT id, username, password_hash, role, is_active FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
            self::recordFailedAttempt();
            $status = isset($user['is_active']) && !$user['is_active'] ? 403 : 401;
            throw new AuthException('Benutzername oder Passwort ist ungültig oder das Konto ist deaktiviert.', $status);
        }

        self::resetRateLimit();
        session_regenerate_id(true);

        $_SESSION[self::SESSION_USER_KEY] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'login_at' => time(),
        ];

        $update = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $update->execute(['id' => $user['id']]);

        return $_SESSION[self::SESSION_USER_KEY];
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    public static function requireLogin(): array
    {
        self::startSession();
        if (empty($_SESSION[self::SESSION_USER_KEY])) {
            Response::jsonError('Nicht angemeldet.', 401);
        }

        return $_SESSION[self::SESSION_USER_KEY];
    }

    /**
     * @param string|string[] $roles
     */
    public static function requireRole(string|array $roles): array
    {
        $user = self::requireLogin();

        $roles = array_values(array_filter((array) $roles, static fn ($role): bool => is_string($role) && $role !== ''));
        if (empty($roles)) {
            Response::jsonError('Keine Berechtigung.', 403);
        }

        if ($user['role'] === 'admin') {
            return $user;
        }

        if (!in_array($user['role'], $roles, true)) {
            Response::jsonError('Keine Berechtigung.', 403);
        }

        return $user;
    }

    private static function enforceRateLimit(): void
    {
        $meta = $_SESSION[self::SESSION_RATE_KEY] ?? ['count' => 0, 'last_attempt' => 0];
        $now = time();

        if ($meta['last_attempt'] < ($now - self::LOGIN_ATTEMPT_WINDOW)) {
            $meta = ['count' => 0, 'last_attempt' => 0];
            $_SESSION[self::SESSION_RATE_KEY] = $meta;
            return;
        }

        if ($meta['count'] >= self::LOGIN_ATTEMPT_LIMIT) {
            throw new AuthException('Zu viele Login-Versuche. Bitte versuche es in einigen Minuten erneut.', 429);
        }
    }

    private static function recordFailedAttempt(): void
    {
        $meta = $_SESSION[self::SESSION_RATE_KEY] ?? ['count' => 0, 'last_attempt' => 0];
        $meta['count'] = ($meta['count'] ?? 0) + 1;
        $meta['last_attempt'] = time();
        $_SESSION[self::SESSION_RATE_KEY] = $meta;
    }

    private static function resetRateLimit(): void
    {
        $_SESSION[self::SESSION_RATE_KEY] = ['count' => 0, 'last_attempt' => 0];
    }
}
