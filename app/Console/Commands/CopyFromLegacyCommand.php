<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Database\LegacyMigration\TableManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time data migration (Feature 002): copies this application's business tables from the
 * `legacy` connection into the target (default) connection, via the query builder only -- never
 * Eloquent, so no model observer fires as a side effect of a copy. See
 * .specs/002-data-migration/spec.md and ADR 0003.
 */
final class CopyFromLegacyCommand extends Command
{
    protected $signature = 'db:copy-from-legacy {--truncate : Replace the target tables\' existing data instead of refusing to run}';

    protected $description = "Copy this application's business tables from the legacy database into the target database.";

    public function handle(): int
    {
        if (! $this->guardOrTruncateTarget()) {
            return self::FAILURE;
        }

        $totalRows = 0;

        foreach (TableManifest::tables() as $table) {
            $rowsCopied = $this->copyTable($table);
            $totalRows += $rowsCopied;
            $this->line(sprintf('%s: %d rows copied', $table, $rowsCopied));

            $this->resetSequenceIfNeeded($table, $rowsCopied);
        }

        $this->info(sprintf('%d tables copied (%d rows)', count(TableManifest::tables()), $totalRows));

        return self::SUCCESS;
    }

    /**
     * Refuses (and reports why) when the target already holds rows in a manifest table, unless
     * --truncate was given, in which case those rows are deleted first. Returns whether the
     * caller should proceed with the copy.
     */
    private function guardOrTruncateTarget(): bool
    {
        $populated = array_values(array_filter(
            TableManifest::tables(),
            fn (string $table): bool => DB::table($table)->count() > 0,
        ));

        if ($populated === []) {
            return true;
        }

        if (! $this->option('truncate')) {
            $this->error(sprintf(
                'The target already holds rows in: %s. Nothing was written. Re-run with --truncate to replace them.',
                implode(', ', $populated),
            ));

            return false;
        }

        foreach ($populated as $table) {
            DB::table($table)->delete();
        }

        return true;
    }

    private function copyTable(string $table): int
    {
        $copied = 0;

        DB::transaction(function () use ($table, &$copied): void {
            DB::connection('legacy')->table($table)
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table, &$copied): void {
                    $data = $rows->map(fn ($row): array => (array) $row)->all();

                    if ($data !== []) {
                        DB::table($table)->insert($data);
                    }

                    $copied += count($data);
                });
        });

        return $copied;
    }

    /**
     * Resets the target's identity sequence so the next insert can't collide with a copied id.
     * A no-op when the target driver isn't PostgreSQL (nothing to reset -- SQLite/MySQL
     * auto-increment already tracks the highest inserted value) or when nothing was copied into
     * this table. $table always comes from TableManifest::tables(), never user input.
     */
    private function resetSequenceIfNeeded(string $table, int $rowsCopied): void
    {
        if ($rowsCopied === 0 || DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            "SELECT setval(pg_get_serial_sequence(?, 'id'), (SELECT MAX(id) FROM {$table}))",
            [$table],
        );
    }
}
