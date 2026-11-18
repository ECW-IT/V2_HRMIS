<?php

namespace Modules\LegacyImport\Console;

use Illuminate\Console\Command;
use Modules\LegacyImport\Services\GeoImporter;
use Throwable;

class ImportGeoCommand extends Command
{
    protected $signature = 'legacy:import-geo
                            {--from-sql : Import from docs/mapping/geo SQL dumps instead of legacy_hrm}';

    protected $description = 'Copy districts and GN divisions into V2 (from legacy_hrm or SQL dumps)';

    public function handle(GeoImporter $importer): int
    {
        try {
            if ($this->option('from-sql')) {
                $base = base_path('docs/mapping/geo');
                $result = $importer->importFromSqlDumps(
                    $base.DIRECTORY_SEPARATOR.'districts.sql',
                    $base.DIRECTORY_SEPARATOR.'gn_divisions.sql',
                );
            } else {
                $result = $importer->importFromLegacy();
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Districts upserted: {$result['districts']}");
        $this->info("GN divisions upserted: {$result['gn_divisions']}");

        return self::SUCCESS;
    }
}
