<?php

declare(strict_types=1);

use App\Actions\Url\UpdateShortUrlAction;
use App\DataTransferObjects\UpdateUrlData;
use App\Enums\UrlStatus;
use App\Exceptions\Url\InvalidUrlException;
use App\Models\ShortUrl;

it('updates url status', function (): void {
    $shortUrl = ShortUrl::factory()->create();
    $action = app(UpdateShortUrlAction::class);
    $data = new UpdateUrlData(status: UrlStatus::Inactive);

    $result = $action->execute($shortUrl, $data);

    expect($result->status)->toBe(UrlStatus::Inactive);
});

it('updates expiration date', function (): void {
    $shortUrl = ShortUrl::factory()->create();
    $action = app(UpdateShortUrlAction::class);
    $newExpiry = now()->addDays(30);
    $data = new UpdateUrlData(expiresAt: $newExpiry);

    $result = $action->execute($shortUrl, $data);

    expect($result->expires_at->toDateString())->toBe($newExpiry->toDateString());
});

it('updates original url', function (): void {
    $shortUrl = ShortUrl::factory()->create();
    $action = app(UpdateShortUrlAction::class);
    $data = new UpdateUrlData(originalUrl: 'https://example.com/new-url');

    $result = $action->execute($shortUrl, $data);

    expect($result->original_url)->toBe('https://example.com/new-url');
});

it('updates url hash when original url changes', function (): void {
    $shortUrl = ShortUrl::factory()->create();
    $action = app(UpdateShortUrlAction::class);
    $newUrl = 'https://example.com/updated-url';
    $expectedHash = hash('sha256', $newUrl);
    $data = new UpdateUrlData(originalUrl: $newUrl);

    $result = $action->execute($shortUrl, $data);

    expect($result->original_url_hash)->toBe($expectedHash);
});

it('throws exception for invalid url', function (): void {
    $shortUrl = ShortUrl::factory()->create();
    $action = app(UpdateShortUrlAction::class);
    $data = new UpdateUrlData(originalUrl: 'not-a-valid-url');

    $action->execute($shortUrl, $data);
})->throws(InvalidUrlException::class);

it('persists changes to database', function (): void {
    $shortUrl = ShortUrl::factory()->create();
    $action = app(UpdateShortUrlAction::class);
    $data = new UpdateUrlData(status: UrlStatus::Inactive);

    $action->execute($shortUrl, $data);

    $this->assertDatabaseHas('short_urls', [
        'id' => $shortUrl->id,
        'status' => 'inactive',
    ]);
});

it('does not change other fields', function (): void {
    $shortUrl = ShortUrl::factory()->withClicks(10)->create();
    $action = app(UpdateShortUrlAction::class);
    $data = new UpdateUrlData(status: UrlStatus::Inactive);

    $result = $action->execute($shortUrl, $data);

    expect($result->clicks)->toBe(10);
});

it('can update multiple fields at once', function (): void {
    $shortUrl = ShortUrl::factory()->create();
    $action = app(UpdateShortUrlAction::class);
    $newExpiry = now()->addDays(14);
    $data = new UpdateUrlData(
        originalUrl: 'https://example.com/multi-update',
        status: UrlStatus::Inactive,
        expiresAt: $newExpiry
    );

    $result = $action->execute($shortUrl, $data);

    expect($result->original_url)->toBe('https://example.com/multi-update')
        ->and($result->status)->toBe(UrlStatus::Inactive)
        ->and($result->expires_at->toDateString())->toBe($newExpiry->toDateString());
});

it('clears expiration when set to null', function (): void {
    $shortUrl = ShortUrl::factory()->expiresInDays(7)->create();
    expect($shortUrl->expires_at)->not->toBeNull();

    $action = app(UpdateShortUrlAction::class);
    $data = UpdateUrlData::fromArray(['expires_at' => null]);

    $result = $action->execute($shortUrl, $data);

    expect($result->expires_at)->toBeNull();
});

it('sanitizes url before updating', function (): void {
    $shortUrl = ShortUrl::factory()->create();
    $action = app(UpdateShortUrlAction::class);
    $data = new UpdateUrlData(originalUrl: '  https://example.com/spaces  ');

    $result = $action->execute($shortUrl, $data);

    expect($result->original_url)->toBe('https://example.com/spaces');
});
