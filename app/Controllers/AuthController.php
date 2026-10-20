<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect('/');
        }

        View::render('auth/login', [
            'appName' => (string) config('app.name', 'Sport Manager'),
        ], null);
    }

    public function login(): void
    {
        if (Auth::check()) {
            redirect('/');
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Please enter a valid email address.';
        }
        if ($password === '') {
            $errors['password'][] = 'Password is required.';
        }

        if ($errors === [] && !Auth::attempt($email, $password)) {
            $errors['email'][] = 'These credentials do not match our records.';
        }

        if ($errors !== []) {
            $_SESSION['old'] = ['email' => $email];
            $_SESSION['errors'] = $errors;
            redirect('/login');
        }

        $user = Auth::user();
        flash('success', 'Welcome back, ' . ($user['name'] ?? 'Admin') . '!');
        redirect('/');
    }

    public function logout(): void
    {
        Auth::logout();
        flash('success', 'You have been signed out. See you soon!');
        redirect('/login');
    }
}
