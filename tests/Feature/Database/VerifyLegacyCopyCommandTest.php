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

it('exits 1 and names the table when row counts differ', function (): void {
    LegacyFixture::seed();
    Artisan::call('db:copy-from-legacy');

    DB::table('short_urls')->where('id', 1)->delete();

    $exitCode = Artisan::call('db:verify-copy');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('short_urls');
});

it('exits 1 and names the table when a single field differs', function (): void {
    LegacyFixture::seed();
    Artisan::call('db:copy-from-legacy');

    DB::table('users')->where('id', 1)->update(['name' => 'Tampered']);

    $exitCode = Artisan::call('db:verify-copy');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('users');
});

it('names every mismatching table, not just the first one found', function (): void {
    LegacyFixture::seed();
    Artisan::call('db:copy-from-legacy');

    DB::table('users')->where('id', 1)->update(['name' => 'Tampered']);
    DB::table('personal_access_tokens')->where('id', 1)->update(['name' => 'Tampered too']);

    $exitCode = Artisan::call('db:verify-copy');
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('users')
        ->and($output)->toContain('personal_access_tokens');
});

it('never writes to a read-only legacy connection', function (): void {
    $path = LegacyFixture::useReadOnlySqliteConnection();

    try {
        Artisan::call('db:copy-from-legacy');
        $verifyExitCode = Artisan::call('db:verify-copy');

        expect($verifyExitCode)->toBe(0);
    } finally {
        @unlink($path);
    }
});
