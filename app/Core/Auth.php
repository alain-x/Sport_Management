<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;

    public static function attempt(string $email, string $password): bool
    {
        $user = User::findByEmail($email);

        if ($user === null || !password_verify($password, $user['password'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        self::$user = null;
        self::$resolved = false;

        return true;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }

        self::$resolved = true;
        $id = $_SESSION['user_id'] ?? null;

        if ($id !== null) {
            self::$user = User::find((int) $id);
        }

        return self::$user;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        self::$user = null;
        self::$resolved = true;
    }
}
