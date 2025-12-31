<?php

declare(strict_types=1);

use App\Actions\Url\CreateShortUrlAction;
use App\DataTransferObjects\CreateUrlData;
use App\Enums\UrlStatus;
use App\Exceptions\Url\InvalidUrlException;
use App\Models\ShortUrl;

it('creates a short url with valid data', function (): void {
    $action = app(CreateShortUrlAction::class);
    $data = new CreateUrlData(
        originalUrl: 'https://example.com/very-long-url-path'
    );

    $shortUrl = $action->execute($data);

    expect($shortUrl)->toBeInstanceOf(ShortUrl::class)
        ->and($shortUrl->original_url)->toBe('https://example.com/very-long-url-path')
        ->and($shortUrl->code)->toHaveLength(6)
        ->and($shortUrl->status)->toBe(UrlStatus::Active)
        ->and($shortUrl->clicks)->toBe(0);
});

it('creates a short url with custom expiration', function (): void {
    $action = app(CreateShortUrlAction::class);
    $expiresAt = now()->addDays(7);
    $data = new CreateUrlData(
        originalUrl: 'https://example.com/expires-test',
        expiresAt: $expiresAt
    );

    $shortUrl = $action->execute($data);

    expect($shortUrl->expires_at)->not->toBeNull()
        ->and($shortUrl->expires_at->toDateString())->toBe($expiresAt->toDateString());
});

it('creates a short url with custom status', function (): void {
    $action = app(CreateShortUrlAction::class);
    $data = new CreateUrlData(
        originalUrl: 'https://example.com/inactive-test',
        status: UrlStatus::Inactive
    );

    $shortUrl = $action->execute($data);

    expect($shortUrl->status)->toBe(UrlStatus::Inactive);
});

it('generates unique code for each url', function (): void {
    $action = app(CreateShortUrlAction::class);

    $shortUrl1 = $action->execute(new CreateUrlData(originalUrl: 'https://example.com/url1'));
    $shortUrl2 = $action->execute(new CreateUrlData(originalUrl: 'https://example.com/url2'));

    expect($shortUrl1->code)->not->toBe($shortUrl2->code);
});

it('calculates url hash correctly', function (): void {
    $action = app(CreateShortUrlAction::class);
    $url = 'https://example.com/hash-test';
    $expectedHash = hash('sha256', $url);

    $shortUrl = $action->execute(new CreateUrlData(originalUrl: $url));

    expect($shortUrl->original_url_hash)->toBe($expectedHash);
});

it('throws exception for invalid url', function (): void {
    $action = app(CreateShortUrlAction::class);
    $data = new CreateUrlData(originalUrl: 'not-a-valid-url');

    $action->execute($data);
})->throws(InvalidUrlException::class);

it('throws exception for url with invalid scheme', function (): void {
    $action = app(CreateShortUrlAction::class);
    $data = new CreateUrlData(originalUrl: 'ftp://files.example.com/file.txt');

    $action->execute($data);
})->throws(InvalidUrlException::class);

it('persists the short url to database', function (): void {
    $action = app(CreateShortUrlAction::class);
    $data = new CreateUrlData(originalUrl: 'https://example.com/persist-test');

    $shortUrl = $action->execute($data);

    $this->assertDatabaseHas('short_urls', [
        'id' => $shortUrl->id,
        'original_url' => 'https://example.com/persist-test',
    ]);
});

it('returns existing url if same url already exists', function (): void {
    $existingUrl = ShortUrl::factory()->withUrl('https://example.com/duplicate')->create();
    $action = app(CreateShortUrlAction::class);
    $data = new CreateUrlData(originalUrl: 'https://example.com/duplicate');

    $shortUrl = $action->execute($data);

    expect($shortUrl->id)->toBe($existingUrl->id)
        ->and($shortUrl->code)->toBe($existingUrl->code);
});

it('sanitizes url before creating', function (): void {
    $action = app(CreateShortUrlAction::class);
    $data = new CreateUrlData(originalUrl: '  https://example.com/spaces  ');

    $shortUrl = $action->execute($data);

    expect($shortUrl->original_url)->toBe('https://example.com/spaces');
});
