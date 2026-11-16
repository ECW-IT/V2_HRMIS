<?php

namespace Modules\LegacyImport\Console;

use Illuminate\Console\Command;
use Modules\LegacyImport\Services\Pay\PayLoader;
use RuntimeException;
use Throwable;

class PayLoadCommand extends Command
{
    protected $signature = 'hrmis:import:pay-load {--company=} {--from=} {--dry-run}';

    protected $description = 'Load staged pay opening data into v2 through domain services';

    public function handle(PayLoader $loader): int
    {
        $from = $this->option('from');
        if (! is_string($from) || $from === '') {
            $this->error('The --from=YYYY-MM-01 option is required.');

            return self::FAILURE;
        }

        try {
            $result = $loader->run(
                $from,
                $this->option('company') ?: null,
                (bool) $this->option('dry-run'),
                $this->laravel['auth']->user(),
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Loaded: '.$result['loaded']);
        $this->info('Skipped: '.$result['skipped']);
        $this->info('Reconciliation file written (private disk).');
        if ($result['dry_run']) {
            $this->info('Dry run — no v2 payroll rows were written.');
        }

        return self::SUCCESS;
    }
}
