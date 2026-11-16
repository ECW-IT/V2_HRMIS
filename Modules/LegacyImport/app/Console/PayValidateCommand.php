<?php

namespace Modules\LegacyImport\Console;

use Illuminate\Console\Command;
use Modules\LegacyImport\Services\Pay\PayValidator;
use RuntimeException;
use Throwable;

class PayValidateCommand extends Command
{
    protected $signature = 'hrmis:import:pay-validate {--company=}';

    protected $description = 'Validate staged pay profiles and write PAY001–PAY012 issues';

    public function handle(PayValidator $validator): int
    {
        try {
            $result = $validator->run($this->option('company') ?: null);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Pay import run #{$result['run']->id}");
        if ($result['counts'] === []) {
            $this->info('No issues.');
        } else {
            foreach ($result['counts'] as $code => $count) {
                $this->info("{$code}: {$count}");
            }
        }

        return self::SUCCESS;
    }
}
