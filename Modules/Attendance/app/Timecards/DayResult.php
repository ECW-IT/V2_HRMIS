<?php

namespace Modules\Attendance\Timecards;

use Carbon\CarbonImmutable;
use Modules\Attendance\Enums\DayType;
use Modules\Attendance\Enums\TimecardException;

final readonly class DayResult
{
    public function __construct(
        public ?CarbonImmutable $firstIn,
        public ?CarbonImmutable $lastOut,
        public int $punchCount,
        public DayType $dayType,
        public float $dayValue,
        public bool $shortLeave,
        public int $lateInMinutes,
        public int $earlyOutMinutes,
        public int $otMorningMinutes,
        public int $otEveningMinutes,
        public ?TimecardException $exception,
    ) {}
}
