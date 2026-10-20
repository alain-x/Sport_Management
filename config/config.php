<?php

declare(strict_types=1);

/**
 * Minimal .env loader + application configuration.
 */

$envFile = dirname(__DIR__) . '/.env';

if (is_file($envFile)) {
    // Loaded inside a closure so parser variables ($key, $value, ...) cannot
    // leak into the scope of whoever requires this file.
    (static function (string $file): void {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$key, $value] = $parts;
            $value = trim($value);
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'") && str_ends_with($value, $value[0])) {
                $value = substr($value, 1, -1);
            }
            $_ENV[strtoupper(trim($key))] = $value;
        }
    })($envFile);
}

error_reporting(E_ALL);
ini_set('display_errors', (($_ENV['APP_DEBUG'] ?? 'false') === 'true') ? '1' : '0');
ini_set('log_errors', '1');

return [
    'app' => [
        'name' => $_ENV['APP_NAME'] ?? 'Sport Manager',
    ],
    'db' => [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
        'name' => $_ENV['DB_NAME'] ?? 'sport_management',
        'user' => $_ENV['DB_USER'] ?? 'root',
        'pass' => $_ENV['DB_PASS'] ?? '',
    ],
];
