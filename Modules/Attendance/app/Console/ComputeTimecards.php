<?php

namespace Modules\Attendance\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Attendance\Services\TimecardService;
use Modules\Organisation\Models\Company;

class ComputeTimecards extends Command
{
    protected $signature = 'attendance:compute-timecards {--from=} {--to=} {--company=}';

    protected $description = 'Compute timecards for a date range';

    public function handle(TimecardService $timecards): int
    {
        $tz = config('app.timezone');
        $today = Carbon::now($tz)->startOfDay();
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'), $tz)->toDateString()
            : $today->copy()->subDays(3)->toDateString();
        $to = $this->option('to')
            ? Carbon::parse($this->option('to'), $tz)->toDateString()
            : $today->copy()->subDay()->toDateString();

        $companies = Company::query()->where('is_active', true)->orderBy('id');
        if ($this->option('company')) {
            $companies->where('id', (int) $this->option('company'));
        }

        $total = 0;
        foreach ($companies->get() as $company) {
            $total += $timecards->computeRange((int) $company->id, $from, $to);
        }

        $this->info("Wrote {$total} timecards.");

        return self::SUCCESS;
    }
}
