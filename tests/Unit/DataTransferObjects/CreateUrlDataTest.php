<?php

declare(strict_types=1);

use App\DataTransferObjects\CreateUrlData;
use App\Enums\UrlStatus;
use Carbon\Carbon;

it('can be instantiated with required parameters', function (): void {
    $dto = new CreateUrlData(originalUrl: 'https://example.com');

    expect($dto->originalUrl)->toBe('https://example.com')
        ->and($dto->customCode)->toBeNull()
        ->and($dto->status)->toBe(UrlStatus::Active)
        ->and($dto->expiresAt)->toBeNull();
});

it('can be instantiated with all parameters', function (): void {
    $expiresAt = Carbon::now()->addDays(7);

    $dto = new CreateUrlData(
        originalUrl: 'https://example.com',
        customCode: 'custom',
        status: UrlStatus::Inactive,
        expiresAt: $expiresAt,
    );

    expect($dto->originalUrl)->toBe('https://example.com')
        ->and($dto->customCode)->toBe('custom')
        ->and($dto->status)->toBe(UrlStatus::Inactive)
        ->and($dto->expiresAt)->toBe($expiresAt);
});

it('can be created from array with minimal data', function (): void {
    $dto = CreateUrlData::fromArray([
        'original_url' => 'https://example.com',
    ]);

    expect($dto->originalUrl)->toBe('https://example.com')
        ->and($dto->customCode)->toBeNull()
        ->and($dto->status)->toBe(UrlStatus::Active);
});

it('can be created from array with full data', function (): void {
    $expiresAt = Carbon::now()->addDays(7);

    $dto = CreateUrlData::fromArray([
        'original_url' => 'https://example.com',
        'custom_code' => 'mycode',
        'status' => UrlStatus::Inactive,
        'expires_at' => $expiresAt,
    ]);

    expect($dto->originalUrl)->toBe('https://example.com')
        ->and($dto->customCode)->toBe('mycode')
        ->and($dto->status)->toBe(UrlStatus::Inactive)
        ->and($dto->expiresAt)->toBe($expiresAt);
});

it('can parse status from string', function (): void {
    $dto = CreateUrlData::fromArray([
        'original_url' => 'https://example.com',
        'status' => 'inactive',
    ]);

    expect($dto->status)->toBe(UrlStatus::Inactive);
});
