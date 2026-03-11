<?php

declare(strict_types=1);

use App\Filament\Resources\ShortUrls\Pages\EditShortUrl;
use App\Filament\Resources\ShortUrls\Pages\ListShortUrls;
use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('QR Code PNG Download from Table', function (): void {
    it('has a download qr png action in the table', function (): void {
        ShortUrl::factory()->create();

        Livewire::test(ListShortUrls::class)
            ->assertSuccessful()
            ->assertTableActionExists('download_qr_png');
    });

    it('can trigger the download qr png action', function (): void {
        $shortUrl = ShortUrl::factory()->create();

        Livewire::test(ListShortUrls::class)
            ->callTableAction('download_qr_png', $shortUrl)
            ->assertSuccessful()
            ->assertFileDownloaded();
    });
});

describe('QR Code SVG Download from Table', function (): void {
    it('has a download qr svg action in the table', function (): void {
        ShortUrl::factory()->create();

        Livewire::test(ListShortUrls::class)
            ->assertSuccessful()
            ->assertTableActionExists('download_qr_svg');
    });

    it('can trigger the download qr svg action', function (): void {
        $shortUrl = ShortUrl::factory()->create();

        Livewire::test(ListShortUrls::class)
            ->callTableAction('download_qr_svg', $shortUrl)
            ->assertSuccessful()
            ->assertFileDownloaded();
    });
});

describe('QR Code PNG Download from Edit Page', function (): void {
    it('has a download qr png header action on edit page', function (): void {
        $shortUrl = ShortUrl::factory()->create();

        Livewire::test(EditShortUrl::class, ['record' => $shortUrl->getRouteKey()])
            ->assertSuccessful()
            ->assertActionExists('download_qr_png');
    });

    it('can trigger the download qr png action from edit page', function (): void {
        $shortUrl = ShortUrl::factory()->create();

        Livewire::test(EditShortUrl::class, ['record' => $shortUrl->getRouteKey()])
            ->callAction('download_qr_png')
            ->assertSuccessful()
            ->assertFileDownloaded();
    });
});

describe('QR Code SVG Download from Edit Page', function (): void {
    it('has a download qr svg header action on edit page', function (): void {
        $shortUrl = ShortUrl::factory()->create();

        Livewire::test(EditShortUrl::class, ['record' => $shortUrl->getRouteKey()])
            ->assertSuccessful()
            ->assertActionExists('download_qr_svg');
    });

    it('can trigger the download qr svg action from edit page', function (): void {
        $shortUrl = ShortUrl::factory()->create();

        Livewire::test(EditShortUrl::class, ['record' => $shortUrl->getRouteKey()])
            ->callAction('download_qr_svg')
            ->assertSuccessful()
            ->assertFileDownloaded();
    });
});
