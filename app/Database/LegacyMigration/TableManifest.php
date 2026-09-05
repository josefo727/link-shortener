<?php

declare(strict_types=1);

namespace App\Database\LegacyMigration;

/**
 * The fixed list of tables the one-time legacy-to-target data migration (Feature 002) copies,
 * and the list it deliberately never touches. See spec.md acceptance criterion 5.
 */
final class TableManifest
{
    /**
     * Business tables to copy, in dependency order.
     *
     * @return list<string>
     */
    public static function tables(): array
    {
        return [
            'users',
            'short_urls',
            'personal_access_tokens',
        ];
    }

    /**
     * Tables the copy never touches: `migrations` (written by `php artisan migrate` itself) and
     * every transient, runtime-state table.
     *
     * @return list<string>
     */
    public static function excluded(): array
    {
        return [
            'migrations',
            'cache',
            'cache_locks',
            'sessions',
            'jobs',
            'job_batches',
            'failed_jobs',
            'password_reset_tokens',
        ];
    }
}
