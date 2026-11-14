<?php

namespace Modules\Calendar\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Calendar\Services\PayPeriodService;

class EnsurePayPeriods extends Command
{
    protected $signature = 'calendar:ensure-periods';

    protected $description = 'Ensure pay periods for every active company from last month to next month';

    public function handle(PayPeriodService $service): int
    {
        $now = Carbon::now(config('app.timezone'));
        $from = $now->copy()->subMonth()->startOfMonth();
        $to = $now->copy()->addMonth()->startOfMonth();

        $created = $service->ensureRange($from, $to);
        $this->info("Created {$created} pay period(s).");

        return self::SUCCESS;
    }
}
