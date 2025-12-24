<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Redis caching of shortened URLs.
    |
    */
    'cache' => [
        'enabled' => env('SHORTENER_CACHE_ENABLED', true),
        'prefix' => 'shorturl',
        'ttl' => env('SHORTENER_CACHE_TTL', 604800), // 1 week in seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Code Generation
    |--------------------------------------------------------------------------
    |
    | Configuration for generating short URL codes.
    |
    */
    'code' => [
        'length' => env('SHORTENER_CODE_LENGTH', 6),
        'alphabet' => env(
            'SHORTENER_CODE_ALPHABET',
            'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
        ),
        'max_attempts' => env('SHORTENER_CODE_MAX_ATTEMPTS', 10),
    ],
];
