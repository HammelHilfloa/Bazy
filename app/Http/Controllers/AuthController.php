<?php

namespace App\Http\Controllers;

use App\Http\Middleware\CsrfMiddleware;
use App\Models\User;
use App\Support\Request;
use App\Support\View;

class AuthController
{
    private User $users;

    public function __construct()
    {
        $this->users = new User();
    }

    public function showLoginForm(Request $request): string
    {
        $errors = $_SESSION['errors'] ?? [];
        unset($_SESSION['errors']);

        return View::make('auth/login', [
            'title' => 'Login',
            'csrf' => CsrfMiddleware::token(),
            'errors' => $errors,
        ]);
    }

    public function login(Request $request): string
    {
        $email = $request->input('email');
        $password = $request->input('password');

        if (!$email || !$password) {
            $_SESSION['errors'] = ['Bitte E-Mail und Passwort angeben.'];
            header('Location: /login');
            exit;
        }

        $user = $this->users->findByEmail($email);
        if (!$user || !password_verify($password, $user['password'])) {
            $_SESSION['errors'] = ['Anmeldedaten ungültig.'];
            header('Location: /login');
            exit;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['name'];
        unset($_SESSION['errors']);

        header('Location: /calendar');
        exit;
    }

    public function logout(Request $request): string
    {
        session_unset();
        session_destroy();
        header('Location: /login');
        exit;
    }
}
