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
    protected $signature = 'db:copy-from-legacy';

    protected $description = "Copy this application's business tables from the legacy database into the target database.";

    public function handle(): int
    {
        $totalRows = 0;

        foreach (TableManifest::tables() as $table) {
            $rowsCopied = $this->copyTable($table);
            $totalRows += $rowsCopied;
            $this->line(sprintf('%s: %d rows copied', $table, $rowsCopied));
        }

        $this->info(sprintf('%d tables copied (%d rows)', count(TableManifest::tables()), $totalRows));

        return self::SUCCESS;
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
