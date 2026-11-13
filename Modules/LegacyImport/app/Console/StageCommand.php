<?php

namespace Modules\LegacyImport\Console;

use Illuminate\Console\Command;
use Modules\LegacyImport\Services\Stager;
use RuntimeException;
use Throwable;

class StageCommand extends Command
{
    protected $signature = 'hrmis:import:stage';

    protected $description = 'Stage active legacy employees into import staging tables';

    public function handle(Stager $stager): int
    {
        try {
            $result = $stager->run();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Import run #{$result['run']->id}");
        $this->info("Legacy active: {$result['legacy_active']}");
        $this->info("Staged: {$result['staged']}");

        return self::SUCCESS;
    }
}
