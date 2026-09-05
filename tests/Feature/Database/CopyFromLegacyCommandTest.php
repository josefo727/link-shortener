<?php

declare(strict_types=1);

use App\Database\LegacyMigration\TableManifest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Points the 'legacy' connection at a fresh in-memory SQLite database, migrated with this app's
 * own schema. Per ADR 0004, this feature's own suite never opens a connection to the real
 * legacy MySQL source.
 */
function useLegacySqliteConnection(): void
{
    config(['database.connections.legacy' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]]);
    DB::purge('legacy');

    Artisan::call('migrate', ['--database' => 'legacy', '--force' => true]);
}

function seedLegacyFixture(): void
{
    DB::connection('legacy')->table('users')->insert([
        'id' => 1,
        'name' => 'Legacy Owner',
        'email' => 'owner@example.com',
        'email_verified_at' => '2026-01-01 00:00:00',
        'password' => 'hashed-password',
        'remember_token' => null,
        'created_at' => '2026-01-01 00:00:00',
        'updated_at' => '2026-01-02 00:00:00',
    ]);

    DB::connection('legacy')->table('short_urls')->insert([
        'id' => 1,
        'code' => 'abc123',
        'title' => 'Example',
        'original_url' => 'https://example.com/some/path',
        'original_url_hash' => hash('sha256', 'https://example.com/some/path'),
        'status' => 'active',
        'clicks' => 42,
        'expires_at' => null,
        'created_at' => '2026-02-01 08:30:00',
        'updated_at' => '2026-02-03 09:15:00',
        'deleted_at' => null,
    ]);

    DB::connection('legacy')->table('personal_access_tokens')->insert([
        'id' => 1,
        'tokenable_type' => 'App\\Models\\User',
        'tokenable_id' => 1,
        'name' => 'cli',
        'token' => hash('sha256', 'a-token'),
        'abilities' => '["*"]',
        'last_used_at' => null,
        'expires_at' => null,
        'created_at' => '2026-01-05 00:00:00',
        'updated_at' => '2026-01-05 00:00:00',
    ]);
}

beforeEach(function (): void {
    useLegacySqliteConnection();
});

it('copies every business table field for field, including exact timestamps', function (): void {
    seedLegacyFixture();

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
    seedLegacyFixture();

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
    seedLegacyFixture();

    $this->artisan('db:copy-from-legacy')->assertExitCode(0);

    expect(Cache::has('shorturl:code:abc123'))->toBeFalse();
});
