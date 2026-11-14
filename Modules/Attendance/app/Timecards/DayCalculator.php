<?php

namespace Modules\Attendance\Timecards;

use Carbon\CarbonImmutable;
use Modules\Attendance\Enums\DayType;
use Modules\Attendance\Enums\OtMode;
use Modules\Attendance\Enums\TimecardException;
use Modules\Attendance\Models\Shift;

final class DayCalculator
{
    /**
     * @param  array<int, array{start: string, end: string, paid: bool}>  $breaks  times as 'H:i' or 'H:i:s'
     * @param  array<int, \DateTimeInterface>  $punches  any order; duplicates allowed
     */
    public function calculate(string $workDate, Shift $shift, array $breaks, OtMode $otMode, bool $lateDeduction, array $punches): DayResult
    {
        $unique = $this->uniqueSorted($punches);
        $count = count($unique);

        if ($count === 0) {
            return $this->empty(DayType::NONE, 0.0, false, null, null, 0, null);
        }

        $first = CarbonImmutable::instance($unique[0])->timezone($this->timezone());
        $last = CarbonImmutable::instance($unique[$count - 1])->timezone($this->timezone());
        $minPresence = $this->minPresenceSeconds();

        if ($count === 1 || $last->getTimestamp() - $first->getTimestamp() < $minPresence) {
            return $this->empty(DayType::NONE, 0.0, false, $first, null, $count, TimecardException::MISSING_PUNCH);
        }

        $start = $shift->at($workDate, 'start_time');
        $end = $shift->at($workDate, 'end_time');
        $lateFrom = $shift->at($workDate, 'late_from');
        $morningCutoff = $shift->at($workDate, 'morning_cutoff');
        $halfDayFrom = $shift->at($workDate, 'half_day_from');
        $earlyLeaveFrom = $shift->at($workDate, 'early_leave_from');

        $byCutoff = $first->lt($morningCutoff->addSeconds(60));

        [$dayType, $dayValue, $shortLeave, $exception] = $this->dayType(
            $first,
            $last,
            $byCutoff,
            $end,
            $halfDayFrom,
            $earlyLeaveFrom,
        );

        $lateIn = 0;
        if ($lateDeduction && $byCutoff && ! $first->lt($lateFrom)) {
            $lateIn = intdiv($first->getTimestamp() - $lateFrom->getTimestamp(), 60) + 1;
        }

        $earlyOut = 0;
        if ($lateDeduction && $shortLeave) {
            $earlyOut = (int) ceil(($end->getTimestamp() - $last->getTimestamp()) / 60);
        }

        $otMorning = 0;
        $otEvening = 0;
        $block = $this->otBlockMinutes();

        if (in_array($otMode, [OtMode::MORNING, OtMode::BOTH], true) && $first->lt($start)) {
            $gross = intdiv($start->getTimestamp() - $first->getTimestamp(), 60);
            $unpaid = $this->unpaidBreakOverlapMinutes($breaks, $workDate, $start, $first, $start);
            $otMorning = $this->roundDownToBlock(max(0, $gross - $unpaid), $block);
        }

        if (in_array($otMode, [OtMode::EVENING, OtMode::BOTH], true) && $last->gt($end)) {
            $gross = intdiv($last->getTimestamp() - $end->getTimestamp(), 60);
            $unpaid = $this->unpaidBreakOverlapMinutes($breaks, $workDate, $start, $end, $last);
            $otEvening = $this->roundDownToBlock(max(0, $gross - $unpaid), $block);
        }

        return new DayResult(
            $first,
            $last,
            $count,
            $dayType,
            $dayValue,
            $shortLeave,
            $lateIn,
            $earlyOut,
            $otMorning,
            $otEvening,
            $exception,
        );
    }

    /**
     * @param  array<int, \DateTimeInterface>  $punches
     * @return list<\DateTimeInterface>
     */
    private function uniqueSorted(array $punches): array
    {
        $keyed = [];
        foreach ($punches as $punch) {
            $at = CarbonImmutable::instance($punch)->timezone($this->timezone());
            $keyed[$at->format('Y-m-d H:i:s')] = $at;
        }
        ksort($keyed);

        return array_values($keyed);
    }

    /**
     * @return array{0: DayType, 1: float, 2: bool, 3: ?TimecardException}
     */
    private function dayType(
        CarbonImmutable $in,
        CarbonImmutable $out,
        bool $byCutoff,
        CarbonImmutable $end,
        CarbonImmutable $halfDayFrom,
        CarbonImmutable $earlyLeaveFrom,
    ): array {
        if ($byCutoff) {
            if (! $out->lt($end)) {
                return [DayType::FULL, 1.0, false, null];
            }
            if (! $out->lt($earlyLeaveFrom) && $out->lt($end)) {
                return [DayType::FULL, 1.0, true, null];
            }
            if (! $out->lt($halfDayFrom) && $out->lt($earlyLeaveFrom)) {
                return [DayType::HALF, 0.5, false, null];
            }

            return [DayType::NONE, 0.0, false, TimecardException::SHORT_PRESENCE];
        }

        if (! $out->lt($earlyLeaveFrom)) {
            return [DayType::HALF, 0.5, false, null];
        }
        if (! $out->lt($halfDayFrom) && $out->lt($earlyLeaveFrom)) {
            return [DayType::SHORT, 0.25, false, null];
        }

        return [DayType::NONE, 0.0, false, TimecardException::SHORT_PRESENCE];
    }

    /**
     * @param  array<int, array{start: string, end: string, paid: bool}>  $breaks
     */
    private function unpaidBreakOverlapMinutes(
        array $breaks,
        string $workDate,
        CarbonImmutable $shiftStart,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
    ): int {
        $total = 0;
        foreach ($breaks as $break) {
            if (! empty($break['paid'])) {
                continue;
            }

            [$bStart, $bEnd] = $this->breakWindow($workDate, $shiftStart, (string) $break['start'], (string) $break['end']);
            $overlapStart = $bStart->max($windowStart);
            $overlapEnd = $bEnd->min($windowEnd);
            if ($overlapEnd->gt($overlapStart)) {
                $total += intdiv($overlapEnd->getTimestamp() - $overlapStart->getTimestamp(), 60);
            }
        }

        return $total;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function breakWindow(string $workDate, CarbonImmutable $shiftStart, string $start, string $end): array
    {
        $tz = $this->timezone();
        $bStart = CarbonImmutable::parse($workDate.' '.$start, $tz);
        $bEnd = CarbonImmutable::parse($workDate.' '.$end, $tz);

        // A break whose start is earlier than shift start is on the next calendar day.
        if ($bStart->lt($shiftStart)) {
            $bStart = $bStart->addDay();
            $bEnd = $bEnd->addDay();
        }

        return [$bStart, $bEnd];
    }

    private function roundDownToBlock(int $minutes, int $block): int
    {
        if ($block <= 0) {
            return $minutes;
        }

        return intdiv($minutes, $block) * $block;
    }

    private function empty(
        DayType $type,
        float $value,
        bool $short,
        ?CarbonImmutable $first,
        ?CarbonImmutable $last,
        int $count,
        ?TimecardException $exception,
    ): DayResult {
        return new DayResult($first, $last, $count, $type, $value, $short, 0, 0, 0, 0, $exception);
    }

    private function timezone(): string
    {
        if (! function_exists('app') || ! app()->bound('config')) {
            return 'Asia/Colombo';
        }

        return (string) config('app.timezone');
    }

    private function minPresenceSeconds(): int
    {
        if (! function_exists('app') || ! app()->bound('config')) {
            return 60;
        }

        return (int) config('attendance.min_presence_seconds', 60);
    }

    private function otBlockMinutes(): int
    {
        if (! function_exists('app') || ! app()->bound('config')) {
            return 30;
        }

        return (int) config('attendance.ot_block_minutes', 30);
    }
}
