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
        'length' => (int) env('SHORTENER_CODE_LENGTH', 6),
        // Alphabet without ambiguous characters: 0/O, 1/l/I, 2/Z, 5/S
        'alphabet' => env(
            'SHORTENER_CODE_ALPHABET',
            'abcdefghjkmnpqrtuvwxyACDEFGHJKMNPQRTUVWXY346789'
        ),
        'max_attempts' => (int) env('SHORTENER_CODE_MAX_ATTEMPTS', 10),
    ],
];
