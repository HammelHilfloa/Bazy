<?php

namespace App\Http\Middleware;

use App\Support\Request;

class AuthMiddleware
{
    public function handle(Request $request, array $params): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
    }

    public function optional(Request $request, array $params): void
    {
        // Allows guests but ensures session array exists.
        $_SESSION['user_id'] = $_SESSION['user_id'] ?? null;
    }
}
