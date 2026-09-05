<?php

declare(strict_types=1);

use App\Database\LegacyMigration\TableManifest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacyFixture;

beforeEach(function (): void {
    LegacyFixture::useSqliteConnection();
});

it('exits 0 and reports a match for every table when source and target agree', function (): void {
    LegacyFixture::seed();
    Artisan::call('db:copy-from-legacy');

    $exitCode = Artisan::call('db:verify-copy');
    $output = Artisan::output();

    expect($exitCode)->toBe(0);

    foreach (TableManifest::tables() as $table) {
        expect($output)->toContain($table);
    }
});

it('never writes to either connection', function (): void {
    LegacyFixture::seed();
    Artisan::call('db:copy-from-legacy');

    $legacyCountsBefore = collect(TableManifest::tables())
        ->mapWithKeys(fn (string $table): array => [$table => DB::connection('legacy')->table($table)->count()]);
    $targetCountsBefore = collect(TableManifest::tables())
        ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);

    Artisan::call('db:verify-copy');

    foreach (TableManifest::tables() as $table) {
        expect(DB::connection('legacy')->table($table)->count())->toBe($legacyCountsBefore[$table])
            ->and(DB::table($table)->count())->toBe($targetCountsBefore[$table]);
    }
});
