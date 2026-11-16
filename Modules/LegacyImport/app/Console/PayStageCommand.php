<?php

namespace Modules\LegacyImport\Console;

use Illuminate\Console\Command;
use Modules\LegacyImport\Services\Pay\PayStager;
use RuntimeException;
use Throwable;

class PayStageCommand extends Command
{
    protected $signature = 'hrmis:import:pay-stage {--company=}';

    protected $description = 'Stage active legacy payroll profiles into pay staging tables';

    public function handle(PayStager $stager): int
    {
        try {
            $result = $stager->run($this->option('company') ?: null);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Pay import run #{$result['run']->id}");
        $this->info("Staged: {$result['staged']}");

        return self::SUCCESS;
    }
}
