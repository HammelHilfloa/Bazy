<?php

namespace App\Http\Middleware;

use App\Support\Request;

class CsrfMiddleware
{
    private const TOKEN_KEY = '_token';

    public function generate(Request $request, array $params): void
    {
        if (empty($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        }
    }

    public function verify(Request $request, array $params): void
    {
        $token = $request->input(self::TOKEN_KEY);
        if (!$token || !hash_equals($_SESSION[self::TOKEN_KEY] ?? '', $token)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }

    public static function token(): string
    {
        return $_SESSION[self::TOKEN_KEY] ?? '';
    }
}
