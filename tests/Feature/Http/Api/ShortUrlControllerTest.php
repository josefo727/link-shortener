<?php

declare(strict_types=1);

use App\Models\ShortUrl;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

describe('Authentication', function (): void {
    it('returns 401 when no token provided for store', function (): void {
        $this->postJson('/api/urls', ['original_url' => 'https://example.com'])
            ->assertUnauthorized();
    });

    it('returns 401 when no token provided for update', function (): void {
        $this->putJson('/api/urls/abc123', ['original_url' => 'https://example.com'])
            ->assertUnauthorized();
    });

    it('returns 401 when no token provided for delete', function (): void {
        $this->deleteJson('/api/urls/abc123')
            ->assertUnauthorized();
    });

    it('returns 401 when invalid token provided', function (): void {
        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->postJson('/api/urls', ['original_url' => 'https://example.com'])
            ->assertUnauthorized();
    });
});

describe('POST /api/urls', function (): void {
    beforeEach(function (): void {
        Sanctum::actingAs($this->user);
    });

    it('creates a new short url', function (): void {
        $this->postJson('/api/urls', [
            'original_url' => 'https://example.com/new-url',
        ])
            ->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'code',
                    'title',
                    'original_url',
                    'short_url',
                    'status',
                    'clicks',
                    'expires_at',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonPath('data.original_url', 'https://example.com/new-url')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.clicks', 0);

        $this->assertDatabaseHas('short_urls', [
            'original_url' => 'https://example.com/new-url',
        ]);
    });

    it('creates short url with custom title', function (): void {
        $this->postJson('/api/urls', [
            'original_url' => 'https://example.com/with-title',
            'title' => 'Mi enlace personalizado',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Mi enlace personalizado');
    });

    it('returns existing url if already exists with 200 status', function (): void {
        $existing = ShortUrl::factory()
            ->withUrl('https://example.com/existing')
            ->create();

        $this->postJson('/api/urls', [
            'original_url' => 'https://example.com/existing',
        ])
            ->assertOk()
            ->assertJsonPath('data.code', $existing->code);

        expect(ShortUrl::count())->toBe(1);
    });

    it('validates required original_url', function (): void {
        $this->postJson('/api/urls', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['original_url']);
    });

    it('validates url format', function (): void {
        $this->postJson('/api/urls', [
            'original_url' => 'not-a-valid-url',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['original_url']);
    });

    it('validates url max length', function (): void {
        $this->postJson('/api/urls', [
            'original_url' => 'https://example.com/'.str_repeat('a', 2050),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['original_url']);
    });

    it('validates title max length', function (): void {
        $this->postJson('/api/urls', [
            'original_url' => 'https://example.com',
            'title' => str_repeat('a', 256),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    });

    it('returns short_url with full domain', function (): void {
        $response = $this->postJson('/api/urls', [
            'original_url' => 'https://example.com',
        ]);

        $data = $response->json('data');
        expect($data['short_url'])->toContain($data['code']);
    });

    it('returns iso8601 formatted dates', function (): void {
        $response = $this->postJson('/api/urls', [
            'original_url' => 'https://example.com',
        ]);

        $data = $response->json('data');
        expect($data['created_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
    });

    it('creates short url with expires_at in ISO 8601 format', function (): void {
        $expiresAt = now()->addDays(7)->toISOString();

        $this->postJson('/api/urls', [
            'original_url' => 'https://example.com/expiring',
            'expires_at' => $expiresAt,
        ])
            ->assertCreated()
            ->assertJsonPath('data.original_url', 'https://example.com/expiring');

        $shortUrl = ShortUrl::where('original_url', 'https://example.com/expiring')->first();
        expect($shortUrl->expires_at)->not->toBeNull();
    });

    it('creates short url with expires_at in date format', function (): void {
        $this->postJson('/api/urls', [
            'original_url' => 'https://example.com/expiring-date',
            'expires_at' => '2025-12-31',
        ])
            ->assertCreated();

        $shortUrl = ShortUrl::where('original_url', 'https://example.com/expiring-date')->first();
        expect($shortUrl->expires_at)->not->toBeNull()
            ->and($shortUrl->expires_at->format('Y-m-d'))->toBe('2025-12-31');
    });

    it('creates short url with null expires_at', function (): void {
        $this->postJson('/api/urls', [
            'original_url' => 'https://example.com/no-expiration',
            'expires_at' => null,
        ])
            ->assertCreated()
            ->assertJsonPath('data.expires_at', null);
    });

    it('validates expires_at format', function (): void {
        $this->postJson('/api/urls', [
            'original_url' => 'https://example.com',
            'expires_at' => 'not-a-date',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expires_at']);
    });
});

describe('PUT /api/urls/{code}', function (): void {
    beforeEach(function (): void {
        Sanctum::actingAs($this->user);
    });

    it('updates original url', function (): void {
        $shortUrl = ShortUrl::factory()
            ->withCode('update1')
            ->withUrl('https://example.com/old')
            ->create();

        $this->putJson('/api/urls/update1', [
            'original_url' => 'https://example.com/new',
        ])
            ->assertOk()
            ->assertJsonPath('data.original_url', 'https://example.com/new')
            ->assertJsonPath('data.code', 'update1');

        $shortUrl->refresh();
        expect($shortUrl->original_url)->toBe('https://example.com/new');
    });

    it('updates title', function (): void {
        $shortUrl = ShortUrl::factory()
            ->withCode('title01')
            ->withTitle('Original title')
            ->create();

        $this->putJson('/api/urls/title01', [
            'title' => 'Updated title',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title');

        $shortUrl->refresh();
        expect($shortUrl->title)->toBe('Updated title');
    });

    it('updates both original_url and title', function (): void {
        ShortUrl::factory()
            ->withCode('both01')
            ->create();

        $this->putJson('/api/urls/both01', [
            'original_url' => 'https://example.com/both-updated',
            'title' => 'Both updated title',
        ])
            ->assertOk()
            ->assertJsonPath('data.original_url', 'https://example.com/both-updated')
            ->assertJsonPath('data.title', 'Both updated title');
    });

    it('returns 404 for non-existent code', function (): void {
        $this->putJson('/api/urls/nonexistent', [
            'original_url' => 'https://example.com',
        ])
            ->assertNotFound()
            ->assertJsonPath('error', 'url_not_found');
    });

    it('validates url format on update', function (): void {
        ShortUrl::factory()->withCode('invalid')->create();

        $this->putJson('/api/urls/invalid', [
            'original_url' => 'not-a-url',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['original_url']);
    });

    it('does not change other fields like clicks', function (): void {
        $shortUrl = ShortUrl::factory()
            ->withCode('keep01')
            ->withClicks(42)
            ->create();

        $this->putJson('/api/urls/keep01', [
            'title' => 'New title only',
        ])
            ->assertOk();

        $shortUrl->refresh();
        expect($shortUrl->clicks)->toBe(42);
    });

    it('cannot update soft deleted url', function (): void {
        $shortUrl = ShortUrl::factory()->withCode('deleted1')->create();
        $shortUrl->delete();

        $this->putJson('/api/urls/deleted1', [
            'original_url' => 'https://example.com',
        ])
            ->assertNotFound();
    });
});

describe('DELETE /api/urls/{code}', function (): void {
    beforeEach(function (): void {
        Sanctum::actingAs($this->user);
    });

    it('soft deletes a short url', function (): void {
        ShortUrl::factory()->withCode('delete1')->create();

        $this->deleteJson('/api/urls/delete1')
            ->assertNoContent();

        $this->assertSoftDeleted('short_urls', ['code' => 'delete1']);
    });

    it('returns 404 for non-existent code', function (): void {
        $this->deleteJson('/api/urls/nonexistent')
            ->assertNotFound()
            ->assertJsonPath('error', 'url_not_found');
    });

    it('returns 404 for already deleted url', function (): void {
        $shortUrl = ShortUrl::factory()->withCode('alreadydel')->create();
        $shortUrl->delete();

        $this->deleteJson('/api/urls/alreadydel')
            ->assertNotFound();
    });

    it('url is no longer accessible via redirect after deletion', function (): void {
        ShortUrl::factory()
            ->withCode('nomore')
            ->withUrl('https://example.com')
            ->create();

        $this->deleteJson('/api/urls/nomore')
            ->assertNoContent();

        $this->get('/nomore')
            ->assertNotFound();
    });
});
