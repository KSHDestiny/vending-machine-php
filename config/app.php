<?php

declare(strict_types=1);

/*
 * Application settings, read from environment variables in this one place.
 * A missing required variable stops the app with an error instead of guessing.
 */

$env = static function (string $name, ?string $default = null): string {
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default ?? throw new RuntimeException("Missing environment variable {$name}");
    }

    return $value;
};

$root = dirname(__DIR__);

return [
    'production' => $env('APP_ENV', 'production') === 'production',
    'db' => [
        'host' => $env('DB_HOST'),
        'port' => (int) $env('DB_PORT', '3306'),
        'name' => $env('DB_NAME'),
        'user' => $env('DB_USER'),
        'password' => $env('DB_PASSWORD'),
    ],
    'jwt' => [
        'secret' => $env('JWT_SECRET'),
        'ttl' => 3600,
    ],
    'paths' => [
        'views' => $root . '/resources/views',
        'migrations' => $root . '/database/migrations',
        'log' => $root . '/storage/logs/app.log',
    ],
];
