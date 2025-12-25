<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ShortUrl;
use App\Services\CacheService;

final readonly class ShortUrlObserver
{
    public function __construct(
        private CacheService $cacheService
    ) {}

    /**
     * Handle the ShortUrl "created" event.
     */
    public function created(ShortUrl $shortUrl): void
    {
        $this->cacheService->put($shortUrl);
    }

    /**
     * Handle the ShortUrl "updated" event.
     */
    public function updated(ShortUrl $shortUrl): void
    {
        // If code changed, remove old code from cache
        if ($shortUrl->wasChanged('code')) {
            $oldCode = $shortUrl->getOriginal('code');
            if (is_string($oldCode)) {
                $this->cacheService->forgetByCode($oldCode);
            }
        }

        // If URL hash changed, remove old hash from cache
        if ($shortUrl->wasChanged('original_url_hash')) {
            $oldHash = $shortUrl->getOriginal('original_url_hash');
            if (is_string($oldHash)) {
                $this->forgetByHash($oldHash);
            }
        }

        // Re-cache with new values
        $this->cacheService->put($shortUrl);
    }

    /**
     * Handle the ShortUrl "deleted" event.
     */
    public function deleted(ShortUrl $shortUrl): void
    {
        $this->cacheService->forget($shortUrl);
    }

    /**
     * Handle the ShortUrl "force deleted" event.
     */
    public function forceDeleted(ShortUrl $shortUrl): void
    {
        $this->cacheService->forget($shortUrl);
    }

    /**
     * Handle the ShortUrl "restored" event.
     */
    public function restored(ShortUrl $shortUrl): void
    {
        $this->cacheService->put($shortUrl);
    }

    /**
     * Remove hash key from cache.
     */
    private function forgetByHash(string $hash): void
    {
        /** @var string $prefix */
        $prefix = config('shortener.cache.prefix', 'shorturl');
        $cacheKey = "{$prefix}:hash:{$hash}";

        \Illuminate\Support\Facades\Cache::forget($cacheKey);
    }
}
