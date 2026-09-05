<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Test-only helper for Feature 002's suite (`db:copy-from-legacy`, `db:verify-copy`): points the
 * 'legacy' connection at a fresh in-memory SQLite database, migrated with this app's own schema,
 * and seeds a small fixture across the three business tables. Per ADR 0004, this feature's own
 * suite never opens a connection to the real legacy MySQL source.
 */
final class LegacyFixture
{
    public static function useSqliteConnection(): void
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

    /**
     * Sets up 'legacy' as a file-based SQLite database (needed so it can be reopened
     * read-only), migrated and seeded, then flips it to SQLite's `PRAGMA query_only` mode.
     * Any write attempted against this connection from that point on raises a real "attempt to
     * write a readonly database" error -- proving contracts/legacy-database.md's read-only
     * guarantee for real, not just by discipline. Returns the temp file path for cleanup.
     */
    public static function useReadOnlySqliteConnection(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'legacy-readonly-').'.sqlite';

        config(['database.connections.legacy' => [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge('legacy');

        Artisan::call('migrate', ['--database' => 'legacy', '--force' => true]);
        self::seed();

        DB::connection('legacy')->statement('PRAGMA query_only = ON');

        return $path;
    }

    public static function seed(): void
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
}
