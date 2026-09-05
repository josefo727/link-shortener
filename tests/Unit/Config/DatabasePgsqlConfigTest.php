<?php

declare(strict_types=1);

it('defaults pgsql sslmode to prefer when DB_SSLMODE is unset', function (): void {
    $original = getenv('DB_SSLMODE');
    putenv('DB_SSLMODE');
    unset($_ENV['DB_SSLMODE'], $_SERVER['DB_SSLMODE']);

    $config = require base_path('config/database.php');

    expect($config['connections']['pgsql']['sslmode'])->toBe('prefer');

    if ($original !== false) {
        putenv("DB_SSLMODE={$original}");
        $_ENV['DB_SSLMODE'] = $original;
        $_SERVER['DB_SSLMODE'] = $original;
    }
});

it('resolves pgsql sslmode from the DB_SSLMODE environment variable', function (): void {
    $original = getenv('DB_SSLMODE');
    putenv('DB_SSLMODE=require');
    $_ENV['DB_SSLMODE'] = 'require';
    $_SERVER['DB_SSLMODE'] = 'require';

    $config = require base_path('config/database.php');

    expect($config['connections']['pgsql']['sslmode'])->toBe('require');

    if ($original === false) {
        putenv('DB_SSLMODE');
        unset($_ENV['DB_SSLMODE'], $_SERVER['DB_SSLMODE']);
    } else {
        putenv("DB_SSLMODE={$original}");
        $_ENV['DB_SSLMODE'] = $original;
        $_SERVER['DB_SSLMODE'] = $original;
    }
});
