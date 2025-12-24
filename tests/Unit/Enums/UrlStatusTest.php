<?php

declare(strict_types=1);

use App\Enums\UrlStatus;

it('has correct values', function (): void {
    expect(UrlStatus::Active->value)->toBe('active')
        ->and(UrlStatus::Inactive->value)->toBe('inactive')
        ->and(UrlStatus::Expired->value)->toBe('expired');
});

it('provides spanish labels', function (): void {
    expect(UrlStatus::Active->label())->toBe('Activo')
        ->and(UrlStatus::Inactive->label())->toBe('Inactivo')
        ->and(UrlStatus::Expired->label())->toBe('Expirado');
});

it('only active status is accessible', function (): void {
    expect(UrlStatus::Active->isAccessible())->toBeTrue()
        ->and(UrlStatus::Inactive->isAccessible())->toBeFalse()
        ->and(UrlStatus::Expired->isAccessible())->toBeFalse();
});

it('can be created from string', function (): void {
    expect(UrlStatus::from('active'))->toBe(UrlStatus::Active)
        ->and(UrlStatus::from('inactive'))->toBe(UrlStatus::Inactive)
        ->and(UrlStatus::from('expired'))->toBe(UrlStatus::Expired);
});
