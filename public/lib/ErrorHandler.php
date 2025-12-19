<?php
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Logger.php';

class ErrorHandler
{
    public static function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
    }

    public static function handleError(int $severity, string $message, ?string $file = null, ?int $line = null): bool
    {
        $context = ['file' => $file, 'line' => $line, 'severity' => $severity];
        Logger::error($message, $context);

        if (php_sapi_name() === 'cli') {
            return false;
        }

        Response::jsonError('Ein unerwarteter Fehler ist aufgetreten.', 500);
        return true;
    }

    public static function handleException(Throwable $exception): void
    {
        Logger::error($exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        if (php_sapi_name() === 'cli') {
            fwrite(STDERR, $exception->getMessage() . PHP_EOL);
            return;
        }

        if (headers_sent()) {
            echo json_encode(['success' => false, 'error' => 'Ein unerwarteter Fehler ist aufgetreten.']);
            return;
        }

        Response::jsonError('Ein unerwarteter Fehler ist aufgetreten.', 500);
    }
}
