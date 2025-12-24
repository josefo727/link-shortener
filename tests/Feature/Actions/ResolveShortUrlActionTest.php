<?php

declare(strict_types=1);

use App\Actions\Url\ResolveShortUrlAction;
use App\Exceptions\Url\UrlNotFoundException;
use App\Models\ShortUrl;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
});

it('resolves a short url by code', function (): void {
    $shortUrl = ShortUrl::factory()->withCode('resolve1')->create();
    $action = app(ResolveShortUrlAction::class);

    $result = $action->execute('resolve1');

    expect($result)->toBeInstanceOf(ShortUrl::class)
        ->and($result->id)->toBe($shortUrl->id)
        ->and($result->code)->toBe('resolve1');
});

it('returns original url', function (): void {
    $shortUrl = ShortUrl::factory()
        ->withCode('geturl')
        ->withUrl('https://example.com/original')
        ->create();
    $action = app(ResolveShortUrlAction::class);

    $result = $action->execute('geturl');

    expect($result->original_url)->toBe('https://example.com/original');
});

it('throws exception when code not found', function (): void {
    $action = app(ResolveShortUrlAction::class);

    $action->execute('nonexistent');
})->throws(UrlNotFoundException::class);

it('throws exception for inactive url', function (): void {
    ShortUrl::factory()->withCode('inactive1')->inactive()->create();
    $action = app(ResolveShortUrlAction::class);

    $action->execute('inactive1');
})->throws(UrlNotFoundException::class);

it('throws exception for expired status url', function (): void {
    ShortUrl::factory()->withCode('expired1')->expired()->create();
    $action = app(ResolveShortUrlAction::class);

    $action->execute('expired1');
})->throws(UrlNotFoundException::class);

it('throws exception for url past expiration date', function (): void {
    ShortUrl::factory()->withCode('pastexp')->expiredAt()->create();
    $action = app(ResolveShortUrlAction::class);

    $action->execute('pastexp');
})->throws(UrlNotFoundException::class);

it('increments click counter', function (): void {
    $shortUrl = ShortUrl::factory()->withCode('clicks1')->create();
    expect($shortUrl->clicks)->toBe(0);

    $action = app(ResolveShortUrlAction::class);
    $action->execute('clicks1');

    $shortUrl->refresh();
    expect($shortUrl->clicks)->toBe(1);
});

it('increments click counter on multiple resolves', function (): void {
    $shortUrl = ShortUrl::factory()->withCode('clicks2')->create();
    $action = app(ResolveShortUrlAction::class);

    $action->execute('clicks2');
    $action->execute('clicks2');
    $action->execute('clicks2');

    $shortUrl->refresh();
    expect($shortUrl->clicks)->toBe(3);
});

it('uses cache when available', function (): void {
    config(['shortener.cache.enabled' => true]);
    $shortUrl = ShortUrl::factory()->withCode('cached1')->create();
    $action = app(ResolveShortUrlAction::class);

    // First call should cache it
    $action->execute('cached1');

    // Verify it's cached
    expect(Cache::has('shorturl:code:cached1'))->toBeTrue();
});

it('resolves url with future expiration', function (): void {
    $shortUrl = ShortUrl::factory()->withCode('future1')->expiresInDays(7)->create();
    $action = app(ResolveShortUrlAction::class);

    $result = $action->execute('future1');

    expect($result->id)->toBe($shortUrl->id);
});
