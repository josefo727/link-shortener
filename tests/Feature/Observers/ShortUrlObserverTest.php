<?php

declare(strict_types=1);

use App\Models\ShortUrl;
use App\Observers\ShortUrlObserver;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
    config(['shortener.cache.enabled' => true]);
});

describe('created', function (): void {
    it('caches the newly created ShortUrl', function (): void {
        $shortUrl = ShortUrl::factory()->withCode('newcache')->create();

        // The observer should have cached it
        $cacheKey = 'shorturl:code:newcache';
        expect(Cache::has($cacheKey))->toBeTrue();
    });
});

describe('updated', function (): void {
    it('updates cache when ShortUrl is updated', function (): void {
        $shortUrl = ShortUrl::factory()->withCode('updateme')->create();

        // Verify initial cache
        expect(Cache::has('shorturl:code:updateme'))->toBeTrue();

        // Update clicks (should update cache)
        $shortUrl->clicks = 10;
        $shortUrl->save();

        $cached = Cache::get('shorturl:code:updateme');
        expect($cached)->not->toBeNull()
            ->and($cached->clicks)->toBe(10);
    });

    it('handles code change by removing old cache and creating new', function (): void {
        $url = 'https://example.com/codechange';
        $shortUrl = ShortUrl::factory()->withUrl($url)->withCode('oldcode')->create();

        expect(Cache::has('shorturl:code:oldcode'))->toBeTrue();

        // Change code
        $shortUrl->code = 'newcode';
        $shortUrl->save();

        expect(Cache::has('shorturl:code:oldcode'))->toBeFalse()
            ->and(Cache::has('shorturl:code:newcode'))->toBeTrue();
    });

    it('handles URL change by updating hash cache', function (): void {
        $oldUrl = 'https://example.com/oldurl';
        $newUrl = 'https://example.com/newurl';
        $oldHash = ShortUrl::hashUrl($oldUrl);
        $newHash = ShortUrl::hashUrl($newUrl);

        $shortUrl = ShortUrl::factory()->withUrl($oldUrl)->withCode('urlchange')->create();

        expect(Cache::has("shorturl:hash:{$oldHash}"))->toBeTrue();

        // Change URL
        $shortUrl->original_url = $newUrl;
        $shortUrl->original_url_hash = $newHash;
        $shortUrl->save();

        expect(Cache::has("shorturl:hash:{$oldHash}"))->toBeFalse()
            ->and(Cache::has("shorturl:hash:{$newHash}"))->toBeTrue();
    });
});

describe('deleted', function (): void {
    it('removes ShortUrl from cache when soft deleted', function (): void {
        $url = 'https://example.com/todelete';
        $hash = ShortUrl::hashUrl($url);
        $shortUrl = ShortUrl::factory()->withUrl($url)->withCode('deleteme')->create();

        expect(Cache::has('shorturl:code:deleteme'))->toBeTrue()
            ->and(Cache::has("shorturl:hash:{$hash}"))->toBeTrue();

        $shortUrl->delete();

        expect(Cache::has('shorturl:code:deleteme'))->toBeFalse()
            ->and(Cache::has("shorturl:hash:{$hash}"))->toBeFalse();
    });
});

describe('forceDeleted', function (): void {
    it('removes ShortUrl from cache when force deleted', function (): void {
        $url = 'https://example.com/toforce';
        $hash = ShortUrl::hashUrl($url);
        $shortUrl = ShortUrl::factory()->withUrl($url)->withCode('forceme')->create();

        expect(Cache::has('shorturl:code:forceme'))->toBeTrue()
            ->and(Cache::has("shorturl:hash:{$hash}"))->toBeTrue();

        $shortUrl->forceDelete();

        expect(Cache::has('shorturl:code:forceme'))->toBeFalse()
            ->and(Cache::has("shorturl:hash:{$hash}"))->toBeFalse();
    });
});

describe('restored', function (): void {
    it('caches ShortUrl when restored from soft delete', function (): void {
        $shortUrl = ShortUrl::factory()->withCode('restore1')->create();
        $shortUrl->delete();

        expect(Cache::has('shorturl:code:restore1'))->toBeFalse();

        $shortUrl->restore();

        expect(Cache::has('shorturl:code:restore1'))->toBeTrue();
    });
});
