<?php

declare(strict_types=1);

use App\Database\LegacyMigration\TableManifest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacyFixture;

beforeEach(function (): void {
    LegacyFixture::useSqliteConnection();
});

it('copies every business table field for field, including exact timestamps', function (): void {
    LegacyFixture::seed();

    $this->artisan('db:copy-from-legacy')->assertExitCode(0);

    $user = DB::table('users')->where('id', 1)->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Legacy Owner')
        ->and($user->email)->toBe('owner@example.com')
        ->and($user->created_at)->toBe('2026-01-01 00:00:00')
        ->and($user->updated_at)->toBe('2026-01-02 00:00:00');

    $shortUrl = DB::table('short_urls')->where('id', 1)->first();
    expect($shortUrl)->not->toBeNull()
        ->and($shortUrl->code)->toBe('abc123')
        ->and($shortUrl->original_url)->toBe('https://example.com/some/path')
        ->and((int) $shortUrl->clicks)->toBe(42)
        ->and($shortUrl->created_at)->toBe('2026-02-01 08:30:00')
        ->and($shortUrl->updated_at)->toBe('2026-02-03 09:15:00');

    $token = DB::table('personal_access_tokens')->where('id', 1)->first();
    expect($token)->not->toBeNull()
        ->and((int) $token->tokenable_id)->toBe(1)
        ->and($token->name)->toBe('cli');
});

it('touches only the manifest tables', function (): void {
    LegacyFixture::seed();

    $this->artisan('db:copy-from-legacy')->assertExitCode(0);

    foreach (TableManifest::excluded() as $table) {
        if ($table === 'migrations') {
            continue; // migrations always has rows once the app boots; not a "copy" concern.
        }

        expect(DB::table($table)->count())->toBe(0);
    }
});

it('never uses Eloquent, so no cache entry appears as a side effect of the copy', function (): void {
    Cache::flush();
    LegacyFixture::seed();

    $this->artisan('db:copy-from-legacy')->assertExitCode(0);

    expect(Cache::has('shorturl:code:abc123'))->toBeFalse();
});

it('refuses to run when the target already holds rows, and writes nothing', function (): void {
    DB::table('short_urls')->insert([
        'code' => 'existing',
        'title' => 'Existing',
        'original_url' => 'https://existing.example.com',
        'original_url_hash' => hash('sha256', 'https://existing.example.com'),
        'status' => 'active',
        'clicks' => 0,
        'created_at' => '2026-01-01 00:00:00',
        'updated_at' => '2026-01-01 00:00:00',
    ]);
    LegacyFixture::seed();

    $exitCode = Artisan::call('db:copy-from-legacy');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('short_urls')
        ->and(DB::table('short_urls')->count())->toBe(1)
        ->and(DB::table('users')->count())->toBe(0)
        ->and(DB::table('personal_access_tokens')->count())->toBe(0);
});

it('replaces the target data cleanly with --truncate', function (): void {
    DB::table('short_urls')->insert([
        'code' => 'stale',
        'title' => 'Stale',
        'original_url' => 'https://stale.example.com',
        'original_url_hash' => hash('sha256', 'https://stale.example.com'),
        'status' => 'active',
        'clicks' => 0,
        'created_at' => '2026-01-01 00:00:00',
        'updated_at' => '2026-01-01 00:00:00',
    ]);
    LegacyFixture::seed();

    $exitCode = Artisan::call('db:copy-from-legacy', ['--truncate' => true]);

    expect($exitCode)->toBe(0)
        ->and(DB::table('short_urls')->count())->toBe(1)
        ->and(DB::table('short_urls')->first()->code)->toBe('abc123');
});

it('stays clean when run with --truncate a second time in a row', function (): void {
    LegacyFixture::seed();

    Artisan::call('db:copy-from-legacy', ['--truncate' => true]);
    $secondRunExitCode = Artisan::call('db:copy-from-legacy', ['--truncate' => true]);

    expect($secondRunExitCode)->toBe(0)
        ->and(DB::table('users')->count())->toBe(1)
        ->and(DB::table('short_urls')->count())->toBe(1)
        ->and(DB::table('personal_access_tokens')->count())->toBe(1);
});

it('resets PostgreSQL identity sequences so a new row never collides with a copied id', function (): void {
    LegacyFixture::seed();
    Artisan::call('db:copy-from-legacy');

    $maxCopiedShortUrlId = (int) DB::table('short_urls')->max('id');
    $maxCopiedUserId = (int) DB::table('users')->max('id');
    $maxCopiedTokenId = (int) DB::table('personal_access_tokens')->max('id');

    $newUserId = DB::table('users')->insertGetId([
        'name' => 'New User',
        'email' => 'new-user@example.com',
        'password' => 'x',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $newShortUrlId = DB::table('short_urls')->insertGetId([
        'code' => 'newcode',
        'title' => 'New',
        'original_url' => 'https://new.example.com',
        'original_url_hash' => hash('sha256', 'https://new.example.com'),
        'status' => 'active',
        'clicks' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $newTokenId = DB::table('personal_access_tokens')->insertGetId([
        'tokenable_type' => 'App\\Models\\User',
        'tokenable_id' => $newUserId,
        'name' => 'new-token',
        'token' => hash('sha256', 'new-token'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($newUserId)->toBeGreaterThan($maxCopiedUserId)
        ->and($newShortUrlId)->toBeGreaterThan($maxCopiedShortUrlId)
        ->and($newTokenId)->toBeGreaterThan($maxCopiedTokenId);
})->skip(
    fn (): bool => DB::connection()->getDriverName() !== 'pgsql',
    'sequence reset is PostgreSQL-specific -- meaningless against SQLite (ADR 0004)',
);

it('never writes to a read-only legacy connection, across the fresh, guard, and --truncate paths', function (): void {
    $path = LegacyFixture::useReadOnlySqliteConnection();

    try {
        $freshExitCode = Artisan::call('db:copy-from-legacy');
        $guardExitCode = Artisan::call('db:copy-from-legacy'); // target now populated, no --truncate
        $truncateExitCode = Artisan::call('db:copy-from-legacy', ['--truncate' => true]);

        expect($freshExitCode)->toBe(0)
            ->and($guardExitCode)->toBe(1)
            ->and($truncateExitCode)->toBe(0);
    } finally {
        @unlink($path);
    }
});
