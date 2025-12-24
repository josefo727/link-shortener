<?php

declare(strict_types=1);

use App\Models\ShortUrl;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
});

it('redirects to original url when code exists', function (): void {
    ShortUrl::factory()
        ->withCode('abc123')
        ->withUrl('https://example.com/long-url')
        ->create();

    $this->get('/abc123')
        ->assertRedirect('https://example.com/long-url')
        ->assertStatus(301);
});

it('returns 404 for non-existent code', function (): void {
    $this->get('/nonexistent')
        ->assertNotFound();
});

it('returns 404 for inactive url', function (): void {
    ShortUrl::factory()
        ->withCode('inactive')
        ->inactive()
        ->create();

    $this->get('/inactive')
        ->assertNotFound();
});

it('returns 404 for expired status url', function (): void {
    ShortUrl::factory()
        ->withCode('expired')
        ->expired()
        ->create();

    $this->get('/expired')
        ->assertNotFound();
});

it('returns 404 for url past expiration date', function (): void {
    ShortUrl::factory()
        ->withCode('pastexp')
        ->expiredAt()
        ->create();

    $this->get('/pastexp')
        ->assertNotFound();
});

it('increments click counter on redirect', function (): void {
    $shortUrl = ShortUrl::factory()
        ->withCode('clicks1')
        ->create();

    expect($shortUrl->clicks)->toBe(0);

    $this->get('/clicks1');

    $shortUrl->refresh();
    expect($shortUrl->clicks)->toBe(1);
});

it('uses permanent redirect 301', function (): void {
    ShortUrl::factory()
        ->withCode('perm301')
        ->withUrl('https://example.com/permanent')
        ->create();

    $this->get('/perm301')
        ->assertStatus(301);
});

it('caches the url on first access', function (): void {
    config(['shortener.cache.enabled' => true]);

    ShortUrl::factory()
        ->withCode('cache1')
        ->withUrl('https://example.com/cached')
        ->create();

    $this->get('/cache1');

    expect(Cache::has('shorturl:code:cache1'))->toBeTrue();
});

it('handles url with special characters', function (): void {
    ShortUrl::factory()
        ->withCode('special')
        ->withUrl('https://example.com/path?query=value&foo=bar#anchor')
        ->create();

    $this->get('/special')
        ->assertRedirect('https://example.com/path?query=value&foo=bar#anchor');
});

it('handles url with unicode characters', function (): void {
    ShortUrl::factory()
        ->withCode('unicode')
        ->withUrl('https://example.com/日本語')
        ->create();

    $this->get('/unicode')
        ->assertRedirect('https://example.com/日本語');
});

it('redirects correctly for url with future expiration', function (): void {
    ShortUrl::factory()
        ->withCode('future')
        ->withUrl('https://example.com/future')
        ->expiresInDays(7)
        ->create();

    $this->get('/future')
        ->assertRedirect('https://example.com/future');
});
