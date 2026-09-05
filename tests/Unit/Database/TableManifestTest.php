<?php

declare(strict_types=1);

use App\Database\LegacyMigration\TableManifest;

it('lists the business tables in dependency order', function (): void {
    expect(TableManifest::tables())->toBe([
        'users',
        'short_urls',
        'personal_access_tokens',
    ]);
});

it('excludes migrations and every transient table', function (): void {
    expect(TableManifest::excluded())->toBe([
        'migrations',
        'cache',
        'cache_locks',
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
    ]);
});

it('never lists the same table in both tables() and excluded()', function (): void {
    $overlap = array_intersect(TableManifest::tables(), TableManifest::excluded());

    expect($overlap)->toBeEmpty();
});
