<?php

namespace Modules\LegacyImport\Console;

use Illuminate\Console\Command;
use Modules\LegacyImport\Services\Reconciler;

class ReportCommand extends Command
{
    protected $signature = 'hrmis:import:report {--run= : Import run id}';

    protected $description = 'Print and write the import reconciliation report';

    public function handle(Reconciler $reconciler): int
    {
        $runId = $this->option('run');
        $runId = is_numeric($runId) ? (int) $runId : null;

        $result = $reconciler->run($runId);

        $this->info("Reconciliation written to {$result['path']}");

        foreach ($result['summary'] as $company => $row) {
            $this->line(sprintf(
                '%s: staged=%d loaded=%d failed=%d legacy_active=%d',
                $company,
                $row['staged'],
                $row['loaded'],
                $row['failed'],
                $row['legacy_active']
            ));
        }

        return self::SUCCESS;
    }
}
