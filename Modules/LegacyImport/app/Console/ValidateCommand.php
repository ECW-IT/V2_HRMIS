<?php

namespace Modules\LegacyImport\Console;

use Illuminate\Console\Command;
use Modules\LegacyImport\Services\Validator;

class ValidateCommand extends Command
{
    protected $signature = 'hrmis:import:validate {--run= : Import run id}';

    protected $description = 'Validate a staged import run and rewrite stg_issues';

    public function handle(Validator $validator): int
    {
        $runId = $this->option('run');
        $runId = is_numeric($runId) ? (int) $runId : null;

        $result = $validator->run($runId);

        $rows = [];
        foreach ($result['counts'] as $code => $count) {
            $rows[] = [$code, $count];
        }

        if ($rows !== []) {
            $this->table(['Code', 'Count'], $rows);
        } else {
            $this->info('No issues.');
        }

        if ($result['errors'] > 0) {
            $this->error("ERROR issues: {$result['errors']}");

            return self::FAILURE;
        }

        $this->info('Validation passed.');

        return self::SUCCESS;
    }
}
