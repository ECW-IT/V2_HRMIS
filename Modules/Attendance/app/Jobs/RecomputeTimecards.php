<?php

namespace Modules\Attendance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Attendance\Exceptions\TimecardLocked;
use Modules\Attendance\Services\TimecardService;

class RecomputeTimecards implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param  list<array{0: int, 1: string}>  $pairs  [[employment_id, work_date], ...] */
    public function __construct(public array $pairs) {}

    public function handle(TimecardService $timecards): void
    {
        foreach ($this->pairs as [$employmentId, $workDate]) {
            try {
                $timecards->compute((int) $employmentId, (string) $workDate);
            } catch (TimecardLocked) {
                // skip
            }
        }
    }
}
