<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CacheServiceInterface;
use App\Models\ShortUrl;
use Illuminate\Support\Facades\Cache;

final readonly class CacheService implements CacheServiceInterface
{
    private string $prefix;

    private int $ttl;

    private bool $enabled;

    public function __construct()
    {
        /** @var string $prefix */
        $prefix = config('shortener.cache.prefix', 'shorturl');
        /** @var int $ttl */
        $ttl = config('shortener.cache.ttl', 604800);
        /** @var bool $enabled */
        $enabled = config('shortener.cache.enabled', true);

        $this->prefix = $prefix;
        $this->ttl = $ttl;
        $this->enabled = $enabled;
    }

    public function getByCode(string $code): ?ShortUrl
    {
        $cacheKey = $this->codeKey($code);

        if ($this->enabled) {
            /** @var ShortUrl|null $cached */
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }
        }

        $shortUrl = ShortUrl::byCode($code)->first();

        if ($shortUrl !== null && $this->enabled) {
            $this->put($shortUrl);
        }

        return $shortUrl;
    }

    public function getCodeByHash(string $hash): ?string
    {
        $cacheKey = $this->hashKey($hash);

        if ($this->enabled) {
            /** @var string|null $cached */
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }
        }

        $shortUrl = ShortUrl::byUrlHash($hash)->first();

        if ($shortUrl === null) {
            return null;
        }

        if ($this->enabled) {
            Cache::put($cacheKey, $shortUrl->code, $this->ttl);
        }

        return $shortUrl->code;
    }

    public function put(ShortUrl $shortUrl): void
    {
        if (! $this->enabled) {
            return;
        }

        Cache::put($this->codeKey($shortUrl->code), $shortUrl, $this->ttl);
        Cache::put($this->hashKey($shortUrl->original_url_hash), $shortUrl->code, $this->ttl);
    }

    public function forget(ShortUrl $shortUrl): void
    {
        if (! $this->enabled) {
            return;
        }

        Cache::forget($this->codeKey($shortUrl->code));
        Cache::forget($this->hashKey($shortUrl->original_url_hash));
    }

    public function forgetByCode(string $code): void
    {
        if (! $this->enabled) {
            return;
        }

        Cache::forget($this->codeKey($code));
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    private function codeKey(string $code): string
    {
        return "{$this->prefix}:code:{$code}";
    }

    private function hashKey(string $hash): string
    {
        return "{$this->prefix}:hash:{$hash}";
    }
}
