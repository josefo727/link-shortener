<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Database\LegacyMigration\TableManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time data migration (Feature 002): compares this application's business tables between the
 * `legacy` connection and the target (default) connection, field by field. Read-only against
 * both. See .specs/002-data-migration/contracts/verification-report.md.
 */
final class VerifyLegacyCopyCommand extends Command
{
    protected $signature = 'db:verify-copy';

    protected $description = "Compare this application's business tables between the legacy database and the target database.";

    public function handle(): int
    {
        foreach (TableManifest::tables() as $table) {
            $mismatch = $this->compareTable($table);

            if ($mismatch !== null) {
                $this->error(sprintf('%s: MISMATCH (%s)', $table, $mismatch));

                return self::FAILURE;
            }

            $this->line(sprintf('%s: match', $table));
        }

        $this->info('All tables match.');

        return self::SUCCESS;
    }

    private function compareTable(string $table): ?string
    {
        $legacyRows = DB::connection('legacy')->table($table)->orderBy('id')->get();
        $targetRows = DB::table($table)->orderBy('id')->get();

        if ($legacyRows->count() !== $targetRows->count()) {
            return sprintf('row count %d vs %d', $legacyRows->count(), $targetRows->count());
        }

        foreach ($legacyRows as $index => $legacyRow) {
            $targetRow = $targetRows[$index];

            if ($this->normalize((array) $legacyRow) !== $this->normalize((array) $targetRow)) {
                return sprintf('row id=%s differs', $legacyRow->id ?? $index);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string|null>
     */
    private function normalize(array $row): array
    {
        return array_map(static function (mixed $value): ?string {
            if ($value === null) {
                return null;
            }

            return is_scalar($value) ? (string) $value : serialize($value);
        }, $row);
    }
}
