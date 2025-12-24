<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\ShortUrl;

interface CacheServiceInterface
{
    /**
     * Get a ShortUrl by its code from cache or database.
     */
    public function getByCode(string $code): ?ShortUrl;

    /**
     * Get a code by URL hash from cache or database.
     */
    public function getCodeByHash(string $hash): ?string;

    /**
     * Store a ShortUrl in cache.
     */
    public function put(ShortUrl $shortUrl): void;

    /**
     * Remove a ShortUrl from cache.
     */
    public function forget(ShortUrl $shortUrl): void;

    /**
     * Remove a ShortUrl from cache by code.
     */
    public function forgetByCode(string $code): void;

    /**
     * Check if caching is enabled.
     */
    public function isEnabled(): bool;
}
