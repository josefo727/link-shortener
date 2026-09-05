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
        $populated = $this->populatedManifestTables();

        if ($populated !== []) {
            if (! $this->option('truncate')) {
                $this->error(sprintf(
                    'The target already holds rows in: %s. Nothing was written. Re-run with --truncate to replace them.',
                    implode(', ', $populated),
                ));

                return self::FAILURE;
            }

            foreach ($populated as $table) {
                DB::table($table)->delete();
            }
        }

        $totalRows = 0;

        foreach (TableManifest::tables() as $table) {
            $rowsCopied = $this->copyTable($table);
            $totalRows += $rowsCopied;
            $this->line(sprintf('%s: %d rows copied', $table, $rowsCopied));
        }

        $this->info(sprintf('%d tables copied (%d rows)', count(TableManifest::tables()), $totalRows));

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function populatedManifestTables(): array
    {
        return array_values(array_filter(
            TableManifest::tables(),
            fn (string $table): bool => DB::table($table)->count() > 0,
        ));
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
}
