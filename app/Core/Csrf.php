<?php

declare(strict_types=1);

namespace App\Core;

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_token'];
    }

    public static function verify(?string $token): bool
    {
        return is_string($token)
            && $token !== ''
            && !empty($_SESSION['_token'])
            && hash_equals($_SESSION['_token'], $token);
    }
}
