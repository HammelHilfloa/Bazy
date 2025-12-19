<?php
require_once __DIR__ . '/Response.php';
class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    /**
        * Startet die PHP-Session mit sicheren Cookie-Optionen.
        */
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

    public static function getToken(): string
    {
        self::startSession();

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    private static function getStoredToken(): ?string
    {
        self::startSession();
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    private static function extractRequestToken(): ?string
    {
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        $bodyToken = $_POST['csrf_token'] ?? null;
        $token = $headerToken ?: $bodyToken;

        return is_string($token) ? $token : null;
    }

    public static function validatePostRequest(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        $token = self::extractRequestToken();
        $stored = self::getStoredToken();

        if (!$token || !$stored || !hash_equals($stored, $token)) {
            Response::jsonError('Ungültiges CSRF-Token.', 400);
        }
    }
}
