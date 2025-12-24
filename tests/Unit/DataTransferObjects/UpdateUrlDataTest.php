<?php

declare(strict_types=1);

use App\DataTransferObjects\UpdateUrlData;
use App\Enums\UrlStatus;
use Carbon\Carbon;

it('can be instantiated with no parameters', function (): void {
    $dto = new UpdateUrlData;

    expect($dto->originalUrl)->toBeNull()
        ->and($dto->status)->toBeNull()
        ->and($dto->expiresAt)->toBeNull();
});

it('can be instantiated with all parameters', function (): void {
    $expiresAt = Carbon::now()->addDays(7);

    $dto = new UpdateUrlData(
        originalUrl: 'https://new-example.com',
        status: UrlStatus::Inactive,
        expiresAt: $expiresAt,
    );

    expect($dto->originalUrl)->toBe('https://new-example.com')
        ->and($dto->status)->toBe(UrlStatus::Inactive)
        ->and($dto->expiresAt)->toBe($expiresAt);
});

it('can be created from array', function (): void {
    $dto = UpdateUrlData::fromArray([
        'original_url' => 'https://new-example.com',
        'status' => 'expired',
    ]);

    expect($dto->originalUrl)->toBe('https://new-example.com')
        ->and($dto->status)->toBe(UrlStatus::Expired);
});

it('detects when it has changes', function (): void {
    $emptyDto = new UpdateUrlData;
    $dtoWithUrl = new UpdateUrlData(originalUrl: 'https://example.com');
    $dtoWithStatus = new UpdateUrlData(status: UrlStatus::Inactive);
    $dtoWithExpiry = new UpdateUrlData(expiresAt: Carbon::now());

    expect($emptyDto->hasChanges())->toBeFalse()
        ->and($dtoWithUrl->hasChanges())->toBeTrue()
        ->and($dtoWithStatus->hasChanges())->toBeTrue()
        ->and($dtoWithExpiry->hasChanges())->toBeTrue();
});
