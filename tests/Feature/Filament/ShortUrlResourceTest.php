<?php

declare(strict_types=1);

use App\Enums\UrlStatus;
use App\Filament\Resources\ShortUrls\Pages\CreateShortUrl;
use App\Filament\Resources\ShortUrls\Pages\EditShortUrl;
use App\Filament\Resources\ShortUrls\Pages\ListShortUrls;
use App\Filament\Resources\ShortUrls\ShortUrlResource;
use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('ListShortUrls Page', function (): void {
    it('can render the index page', function (): void {
        Livewire::test(ListShortUrls::class)
            ->assertSuccessful();
    });

    it('displays short urls in the table', function (): void {
        $shortUrls = ShortUrl::factory()->count(3)->create();

        Livewire::test(ListShortUrls::class)
            ->assertSuccessful()
            ->assertSee($shortUrls[0]->code)
            ->assertSee($shortUrls[1]->code)
            ->assertSee($shortUrls[2]->code);
    });

    it('displays status badges', function (): void {
        ShortUrl::factory()->create(['status' => UrlStatus::Active]);
        ShortUrl::factory()->create(['status' => UrlStatus::Inactive]);
        ShortUrl::factory()->create(['status' => UrlStatus::Expired]);

        Livewire::test(ListShortUrls::class)
            ->assertSuccessful()
            ->assertSee('Activo')
            ->assertSee('Inactivo')
            ->assertSee('Expirado');
    });

    it('displays click count', function (): void {
        ShortUrl::factory()->create(['clicks' => 42]);

        Livewire::test(ListShortUrls::class)
            ->assertSuccessful()
            ->assertSee('42');
    });

    it('can search by code', function (): void {
        $target = ShortUrl::factory()->create(['code' => 'findme1']);
        ShortUrl::factory()->create(['code' => 'other99']);

        Livewire::test(ListShortUrls::class)
            ->searchTable('findme')
            ->assertSee('findme1')
            ->assertDontSee('other99');
    });

    it('can filter by status', function (): void {
        ShortUrl::factory()->create(['status' => UrlStatus::Active, 'code' => 'active1']);
        ShortUrl::factory()->create(['status' => UrlStatus::Inactive, 'code' => 'inactive1']);

        Livewire::test(ListShortUrls::class)
            ->filterTable('status', UrlStatus::Active->value)
            ->assertSee('active1')
            ->assertDontSee('inactive1');
    });
});

describe('CreateShortUrl Page', function (): void {
    it('can render the create page', function (): void {
        Livewire::test(CreateShortUrl::class)
            ->assertSuccessful();
    });

    it('can create a short url', function (): void {
        Livewire::test(CreateShortUrl::class)
            ->fillForm([
                'original_url' => 'https://example.com',
                'status' => UrlStatus::Active->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(ShortUrl::count())->toBe(1);

        $shortUrl = ShortUrl::first();
        expect($shortUrl->original_url)->toBe('https://example.com');
        expect($shortUrl->status)->toBe(UrlStatus::Active);
    });

    it('validates required original_url', function (): void {
        Livewire::test(CreateShortUrl::class)
            ->fillForm([
                'original_url' => '',
                'status' => UrlStatus::Active->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['original_url' => 'required']);

        expect(ShortUrl::count())->toBe(0);
    });

    it('validates url format', function (): void {
        Livewire::test(CreateShortUrl::class)
            ->fillForm([
                'original_url' => 'not-a-valid-url',
                'status' => UrlStatus::Active->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['original_url' => 'url']);

        expect(ShortUrl::count())->toBe(0);
    });

    it('can create with inactive status', function (): void {
        Livewire::test(CreateShortUrl::class)
            ->fillForm([
                'original_url' => 'https://example.com',
                'status' => UrlStatus::Inactive->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $shortUrl = ShortUrl::first();
        expect($shortUrl->status)->toBe(UrlStatus::Inactive);
    });

    it('can create with expiration date', function (): void {
        $expiresAt = now()->addDays(7);

        Livewire::test(CreateShortUrl::class)
            ->fillForm([
                'original_url' => 'https://example.com',
                'status' => UrlStatus::Active->value,
                'expires_at' => $expiresAt,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $shortUrl = ShortUrl::first();
        expect($shortUrl->expires_at)->not->toBeNull();
    });
});

describe('EditShortUrl Page', function (): void {
    it('can render the edit page', function (): void {
        $shortUrl = ShortUrl::factory()->create();

        Livewire::test(EditShortUrl::class, ['record' => $shortUrl->getRouteKey()])
            ->assertSuccessful();
    });

    it('displays current values in form', function (): void {
        $shortUrl = ShortUrl::factory()->create([
            'original_url' => 'https://example.com/test',
            'status' => UrlStatus::Active,
        ]);

        Livewire::test(EditShortUrl::class, ['record' => $shortUrl->getRouteKey()])
            ->assertFormFieldExists('original_url')
            ->assertFormSet([
                'original_url' => 'https://example.com/test',
                'status' => UrlStatus::Active->value,
            ]);
    });

    it('displays code placeholder', function (): void {
        $shortUrl = ShortUrl::factory()->create(['code' => 'abc123']);

        Livewire::test(EditShortUrl::class, ['record' => $shortUrl->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('abc123');
    });

    it('displays clicks count', function (): void {
        $shortUrl = ShortUrl::factory()->create(['clicks' => 42]);

        Livewire::test(EditShortUrl::class, ['record' => $shortUrl->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('42');
    });

    it('can update original url', function (): void {
        $shortUrl = ShortUrl::factory()->create([
            'original_url' => 'https://example.com/old',
        ]);

        Livewire::test(EditShortUrl::class, ['record' => $shortUrl->getRouteKey()])
            ->fillForm([
                'original_url' => 'https://example.com/new',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $shortUrl->refresh();
        expect($shortUrl->original_url)->toBe('https://example.com/new');
    });

    it('can update status', function (): void {
        $shortUrl = ShortUrl::factory()->create([
            'status' => UrlStatus::Active,
        ]);

        Livewire::test(EditShortUrl::class, ['record' => $shortUrl->getRouteKey()])
            ->fillForm([
                'status' => UrlStatus::Inactive->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $shortUrl->refresh();
        expect($shortUrl->status)->toBe(UrlStatus::Inactive);
    });

    it('can set expiration date', function (): void {
        $shortUrl = ShortUrl::factory()->create([
            'expires_at' => null,
        ]);

        Livewire::test(EditShortUrl::class, ['record' => $shortUrl->getRouteKey()])
            ->fillForm([
                'expires_at' => now()->addDays(30),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $shortUrl->refresh();
        expect($shortUrl->expires_at)->not->toBeNull();
    });

    it('can clear expiration date', function (): void {
        $shortUrl = ShortUrl::factory()->create([
            'expires_at' => now()->addDays(7),
        ]);

        Livewire::test(EditShortUrl::class, ['record' => $shortUrl->getRouteKey()])
            ->fillForm([
                'expires_at' => null,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $shortUrl->refresh();
        expect($shortUrl->expires_at)->toBeNull();
    });

    it('can edit soft deleted record', function (): void {
        $shortUrl = ShortUrl::factory()->create();
        $shortUrl->delete();

        Livewire::test(EditShortUrl::class, ['record' => $shortUrl->getRouteKey()])
            ->assertSuccessful();
    });
});

describe('ShortUrlResource Configuration', function (): void {
    it('has correct model', function (): void {
        expect(ShortUrlResource::getModel())->toBe(ShortUrl::class);
    });

    it('has correct navigation label', function (): void {
        expect(ShortUrlResource::getNavigationLabel())->toBe('Enlaces Cortos');
    });

    it('has correct model label', function (): void {
        expect(ShortUrlResource::getModelLabel())->toBe('Enlace Corto');
    });

    it('has correct plural model label', function (): void {
        expect(ShortUrlResource::getPluralModelLabel())->toBe('Enlaces Cortos');
    });

    it('has correct pages configured', function (): void {
        $pages = ShortUrlResource::getPages();

        expect($pages)->toHaveKeys(['index', 'create', 'edit']);
    });
});
