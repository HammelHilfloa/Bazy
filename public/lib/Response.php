<?php
class Response
{
    public static function jsonSuccess(array $data = [], int $status = 200): void
    {
        self::send(['success' => true, 'data' => $data], $status);
    }

    public static function jsonError(string $message, int $status = 400, array $meta = []): void
    {
        self::send(['success' => false, 'error' => $message, 'meta' => $meta], $status);
    }

    public static function send(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
