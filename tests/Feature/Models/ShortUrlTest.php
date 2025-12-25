<?php

declare(strict_types=1);

use App\Enums\UrlStatus;
use App\Models\ShortUrl;

it('can be created with factory', function (): void {
    $shortUrl = ShortUrl::factory()->create();

    expect($shortUrl)->toBeInstanceOf(ShortUrl::class)
        ->and($shortUrl->id)->toBeInt()
        ->and($shortUrl->code)->toBeString()
        ->and($shortUrl->original_url)->toBeString()
        ->and($shortUrl->original_url_hash)->toHaveLength(64)
        ->and($shortUrl->status)->toBe(UrlStatus::Active)
        ->and($shortUrl->clicks)->toBe(0);
});

it('casts status to UrlStatus enum', function (): void {
    $shortUrl = ShortUrl::factory()->create();

    expect($shortUrl->status)->toBeInstanceOf(UrlStatus::class);
});

it('casts expires_at to Carbon', function (): void {
    $shortUrl = ShortUrl::factory()->expiresInDays(7)->create();

    expect($shortUrl->expires_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('generates correct hash for url', function (): void {
    $url = 'https://example.com';
    $expectedHash = hash('sha256', $url);

    expect(ShortUrl::hashUrl($url))->toBe($expectedHash);
});

describe('isAccessible', function (): void {
    it('returns true for active non-expired url', function (): void {
        $shortUrl = ShortUrl::factory()->create();

        expect($shortUrl->isAccessible())->toBeTrue();
    });

    it('returns true for active url with future expiration', function (): void {
        $shortUrl = ShortUrl::factory()->expiresInDays(7)->create();

        expect($shortUrl->isAccessible())->toBeTrue();
    });

    it('returns false for inactive url', function (): void {
        $shortUrl = ShortUrl::factory()->inactive()->create();

        expect($shortUrl->isAccessible())->toBeFalse();
    });

    it('returns false for expired status url', function (): void {
        $shortUrl = ShortUrl::factory()->expired()->create();

        expect($shortUrl->isAccessible())->toBeFalse();
    });

    it('returns false for url past expiration date', function (): void {
        $shortUrl = ShortUrl::factory()->expiredAt()->create();

        expect($shortUrl->isAccessible())->toBeFalse();
    });
});

describe('isExpired', function (): void {
    it('returns false when no expiration set', function (): void {
        $shortUrl = ShortUrl::factory()->create();

        expect($shortUrl->isExpired())->toBeFalse();
    });

    it('returns false when expiration is in future', function (): void {
        $shortUrl = ShortUrl::factory()->expiresInDays(7)->create();

        expect($shortUrl->isExpired())->toBeFalse();
    });

    it('returns true when expiration is in past', function (): void {
        $shortUrl = ShortUrl::factory()->expiredAt()->create();

        expect($shortUrl->isExpired())->toBeTrue();
    });
});

it('can increment clicks', function (): void {
    $shortUrl = ShortUrl::factory()->create();

    expect($shortUrl->clicks)->toBe(0);

    $shortUrl->incrementClicks();
    $shortUrl->refresh();

    expect($shortUrl->clicks)->toBe(1);

    $shortUrl->incrementClicks();
    $shortUrl->refresh();

    expect($shortUrl->clicks)->toBe(2);
});

describe('scopes', function (): void {
    it('can find by code', function (): void {
        $shortUrl = ShortUrl::factory()->withCode('abc123')->create();
        ShortUrl::factory()->create();

        $found = ShortUrl::byCode('abc123')->first();

        expect($found->id)->toBe($shortUrl->id);
    });

    it('can find by url hash', function (): void {
        $url = 'https://unique-url.com';
        $shortUrl = ShortUrl::factory()->withUrl($url)->create();
        ShortUrl::factory()->create();

        $hash = ShortUrl::hashUrl($url);
        $found = ShortUrl::byUrlHash($hash)->first();

        expect($found->id)->toBe($shortUrl->id);
    });

    it('can filter active urls', function (): void {
        ShortUrl::factory()->create();
        ShortUrl::factory()->inactive()->create();
        ShortUrl::factory()->expired()->create();

        $activeUrls = ShortUrl::active()->get();

        expect($activeUrls)->toHaveCount(1);
    });

    it('can filter accessible urls', function (): void {
        ShortUrl::factory()->create(); // active, no expiry
        ShortUrl::factory()->expiresInDays(7)->create(); // active, future expiry
        ShortUrl::factory()->expiredAt()->create(); // active but past expiry
        ShortUrl::factory()->inactive()->create();

        $accessibleUrls = ShortUrl::accessible()->get();

        expect($accessibleUrls)->toHaveCount(2);
    });
});

it('uses soft deletes', function (): void {
    $shortUrl = ShortUrl::factory()->create();
    $id = $shortUrl->id;

    $shortUrl->delete();

    expect(ShortUrl::find($id))->toBeNull()
        ->and(ShortUrl::withTrashed()->find($id))->not->toBeNull();
});

describe('title auto-generation', function (): void {
    it('generates title from url path when not provided', function (): void {
        $shortUrl = ShortUrl::factory()
            ->withUrl('https://example.com/docs/api-reference')
            ->withoutTitle()
            ->create();

        expect($shortUrl->title)->toBe('docs/api-reference');
    });

    it('uses host when url has no path', function (): void {
        $shortUrl = ShortUrl::factory()
            ->withUrl('https://google.com')
            ->withoutTitle()
            ->create();

        expect($shortUrl->title)->toBe('google.com');
    });

    it('uses host when path is only slash', function (): void {
        $shortUrl = ShortUrl::factory()
            ->withUrl('https://example.com/')
            ->withoutTitle()
            ->create();

        expect($shortUrl->title)->toBe('example.com');
    });

    it('preserves custom title when provided', function (): void {
        $shortUrl = ShortUrl::factory()
            ->withUrl('https://example.com/path')
            ->withTitle('Mi titulo personalizado')
            ->create();

        expect($shortUrl->title)->toBe('Mi titulo personalizado');
    });

    it('generates correct title from complex url', function (): void {
        $shortUrl = ShortUrl::factory()
            ->withUrl('https://github.com/laravel/framework')
            ->withoutTitle()
            ->create();

        expect($shortUrl->title)->toBe('laravel/framework');
    });
});
