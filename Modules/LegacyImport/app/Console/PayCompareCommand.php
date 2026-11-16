<?php

namespace Modules\LegacyImport\Console;

use Illuminate\Console\Command;
use Modules\LegacyImport\Services\Pay\PayComparer;
use RuntimeException;
use Throwable;

class PayCompareCommand extends Command
{
    protected $signature = 'hrmis:compare:legacy-pay {period} {--company=}';

    protected $description = 'Compare a v2 pay run with the legacy paysheet for a period';

    public function handle(PayComparer $comparer): int
    {
        try {
            $result = $comparer->run((string) $this->argument('period'), $this->option('company') ?: null);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Employees compared: '.$result['compared']);
        $this->info('Matched: '.$result['matched']);
        $this->info('Different: '.$result['different']);
        $this->info('Only in v2: '.$result['only_v2']);
        $this->info('Only in legacy: '.$result['only_legacy']);

        return self::SUCCESS;
    }
}
