<?php

namespace Modules\LegacyImport\Console;

use Illuminate\Console\Command;
use Modules\LegacyImport\Services\Loader;
use RuntimeException;

class LoadCommand extends Command
{
    protected $signature = 'hrmis:import:load {--run= : Import run id} {--dry-run : Roll back every employee transaction}';

    protected $description = 'Load a validated import run through domain services';

    public function handle(Loader $loader): int
    {
        $runId = $this->option('run');
        $runId = is_numeric($runId) ? (int) $runId : null;
        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $loader->run($runId, $dryRun);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Loaded: {$result['loaded']}");
        $this->info("Failed: {$result['failed']}");
        if ($dryRun) {
            $this->info('Dry run — nothing saved.');
        }

        return self::SUCCESS;
    }
}
