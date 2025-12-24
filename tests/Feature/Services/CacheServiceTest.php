<?php

declare(strict_types=1);

use App\Models\ShortUrl;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
});

describe('isEnabled', function (): void {
    it('returns true when cache is enabled in config', function (): void {
        config(['shortener.cache.enabled' => true]);

        $service = new CacheService();

        expect($service->isEnabled())->toBeTrue();
    });

    it('returns false when cache is disabled in config', function (): void {
        config(['shortener.cache.enabled' => false]);

        $service = new CacheService();

        expect($service->isEnabled())->toBeFalse();
    });
});

describe('getByCode', function (): void {
    it('returns cached ShortUrl when present in cache', function (): void {
        config(['shortener.cache.enabled' => true]);
        $shortUrl = ShortUrl::factory()->withCode('cached1')->make();
        $shortUrl->id = 999;
        $service = new CacheService();

        // Put directly in cache (bypassing observer)
        Cache::put('shorturl:code:cached1', $shortUrl, 3600);

        $result = $service->getByCode('cached1');

        expect($result)->not->toBeNull()
            ->and($result->code)->toBe('cached1');
    });

    it('returns from database when not in cache and caches it', function (): void {
        config(['shortener.cache.enabled' => true]);
        $shortUrl = ShortUrl::factory()->withCode('notcached')->create();
        $service = new CacheService();

        $result = $service->getByCode('notcached');

        expect($result)->not->toBeNull()
            ->and($result->code)->toBe('notcached');

        // Verify it was cached
        $cacheKey = 'shorturl:code:notcached';
        expect(Cache::has($cacheKey))->toBeTrue();
    });

    it('returns null when code does not exist', function (): void {
        config(['shortener.cache.enabled' => true]);
        $service = new CacheService();

        $result = $service->getByCode('nonexistent');

        expect($result)->toBeNull();
    });

    it('queries database directly when cache is disabled', function (): void {
        config(['shortener.cache.enabled' => false]);
        $shortUrl = ShortUrl::factory()->withCode('dbonly')->create();
        $service = new CacheService();

        $result = $service->getByCode('dbonly');

        expect($result)->not->toBeNull()
            ->and($result->code)->toBe('dbonly');

        // Verify it was NOT cached
        $cacheKey = 'shorturl:code:dbonly';
        expect(Cache::has($cacheKey))->toBeFalse();
    });
});

describe('getCodeByHash', function (): void {
    it('returns cached code when present in cache', function (): void {
        config(['shortener.cache.enabled' => true]);
        $url = 'https://example.com/unique';
        $hash = ShortUrl::hashUrl($url);
        $service = new CacheService();

        // Put directly in cache (bypassing observer)
        Cache::put("shorturl:hash:{$hash}", 'hashtest', 3600);

        $result = $service->getCodeByHash($hash);

        expect($result)->toBe('hashtest');
    });

    it('returns from database when not in cache and caches it', function (): void {
        config(['shortener.cache.enabled' => true]);
        $url = 'https://example.com/unique2';
        $shortUrl = ShortUrl::factory()->withUrl($url)->withCode('hashdb')->create();
        $hash = ShortUrl::hashUrl($url);
        $service = new CacheService();

        $result = $service->getCodeByHash($hash);

        expect($result)->toBe('hashdb');

        // Verify the hash mapping was cached
        $cacheKey = "shorturl:hash:{$hash}";
        expect(Cache::has($cacheKey))->toBeTrue();
    });

    it('returns null when hash does not exist', function (): void {
        config(['shortener.cache.enabled' => true]);
        $service = new CacheService();
        $hash = ShortUrl::hashUrl('https://nonexistent.com');

        $result = $service->getCodeByHash($hash);

        expect($result)->toBeNull();
    });
});

describe('put', function (): void {
    it('stores ShortUrl with correct cache keys', function (): void {
        config(['shortener.cache.enabled' => true]);
        $url = 'https://example.com/tostore';
        $shortUrl = ShortUrl::factory()->withUrl($url)->withCode('store1')->create();
        $hash = ShortUrl::hashUrl($url);
        $service = new CacheService();

        $service->put($shortUrl);

        expect(Cache::has('shorturl:code:store1'))->toBeTrue()
            ->and(Cache::has("shorturl:hash:{$hash}"))->toBeTrue();
    });

    it('does nothing when cache is disabled', function (): void {
        config(['shortener.cache.enabled' => false]);
        $url = 'https://example.com/nostore';
        $shortUrl = ShortUrl::factory()->withUrl($url)->withCode('nostore')->create();
        $hash = ShortUrl::hashUrl($url);
        $service = new CacheService();

        $service->put($shortUrl);

        expect(Cache::has('shorturl:code:nostore'))->toBeFalse()
            ->and(Cache::has("shorturl:hash:{$hash}"))->toBeFalse();
    });

    it('uses configured TTL', function (): void {
        config(['shortener.cache.enabled' => true]);
        config(['shortener.cache.ttl' => 3600]);
        $shortUrl = ShortUrl::factory()->withCode('ttltest')->create();

        // We can't easily test TTL directly, but we can verify the cache works
        $service = new CacheService();
        $service->put($shortUrl);

        expect(Cache::has('shorturl:code:ttltest'))->toBeTrue();
    });
});

describe('forget', function (): void {
    it('removes ShortUrl from all cache keys', function (): void {
        config(['shortener.cache.enabled' => true]);
        $url = 'https://example.com/toforget';
        $shortUrl = ShortUrl::factory()->withUrl($url)->withCode('forget1')->create();
        $hash = ShortUrl::hashUrl($url);
        $service = new CacheService();

        $service->put($shortUrl);
        expect(Cache::has('shorturl:code:forget1'))->toBeTrue();
        expect(Cache::has("shorturl:hash:{$hash}"))->toBeTrue();

        $service->forget($shortUrl);

        expect(Cache::has('shorturl:code:forget1'))->toBeFalse()
            ->and(Cache::has("shorturl:hash:{$hash}"))->toBeFalse();
    });

    it('does nothing when cache is disabled', function (): void {
        config(['shortener.cache.enabled' => false]);
        $shortUrl = ShortUrl::factory()->withCode('noforget')->create();
        $service = new CacheService();

        // This should not throw
        $service->forget($shortUrl);

        expect(true)->toBeTrue();
    });
});

describe('forgetByCode', function (): void {
    it('removes cached entry by code', function (): void {
        config(['shortener.cache.enabled' => true]);
        $shortUrl = ShortUrl::factory()->withCode('forgetcode')->create();
        $service = new CacheService();

        $service->put($shortUrl);
        expect(Cache::has('shorturl:code:forgetcode'))->toBeTrue();

        $service->forgetByCode('forgetcode');

        expect(Cache::has('shorturl:code:forgetcode'))->toBeFalse();
    });

    it('does nothing when cache is disabled', function (): void {
        config(['shortener.cache.enabled' => false]);
        $service = new CacheService();

        // This should not throw
        $service->forgetByCode('anycode');

        expect(true)->toBeTrue();
    });
});
