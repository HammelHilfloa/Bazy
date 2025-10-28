<?php

namespace App\Support;

class Request
{
    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = strtok($uri, '?');
        return rtrim($uri, '/') ?: '/';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public function all(): array
    {
        return match ($this->method()) {
            'POST', 'PUT', 'PATCH' => $_POST,
            default => $_GET,
        };
    }

    public function session(): array
    {
        return $_SESSION;
    }
}
