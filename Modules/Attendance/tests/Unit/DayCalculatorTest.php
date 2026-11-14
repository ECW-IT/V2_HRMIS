<?php

namespace Modules\Attendance\Tests\Unit;

use Carbon\CarbonImmutable;
use Modules\Attendance\Enums\DayType;
use Modules\Attendance\Enums\OtMode;
use Modules\Attendance\Enums\TimecardException;
use Modules\Attendance\Models\Shift;
use Modules\Attendance\Timecards\DayCalculator;
use Modules\Attendance\Timecards\DayResult;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DayCalculatorTest extends TestCase
{
    private string $workDate = '2026-12-01';

    private function calc(array $times, OtMode $ot = OtMode::NONE, bool $late = false, ?array $breaks = null, array $shift = []): DayResult
    {
        $defaults = [
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'late_from' => '08:06:00',
            'morning_cutoff' => '10:00:00',
            'half_day_from' => '12:30:00',
            'early_leave_from' => '15:00:00',
        ];
        $s = new Shift(array_merge($defaults, $shift));
        $breakRows = $breaks ?? [['start' => '20:00', 'end' => '20:30', 'paid' => false]];

        $punches = [];
        foreach ($times as $time) {
            if (str_starts_with($time, '+')) {
                $punches[] = CarbonImmutable::parse($this->workDate.' '.substr($time, 1))->addDay();
            } else {
                $punches[] = CarbonImmutable::parse($this->workDate.' '.$time);
            }
        }

        return (new DayCalculator)->calculate($this->workDate, $s, $breakRows, $ot, $late, $punches);
    }

    public function test_no_punches_is_absent_without_exception(): void
    {
        $r = $this->calc([]);
        $this->assertSame([DayType::NONE, 0.0, 0, null, null], [$r->dayType, $r->dayValue, $r->punchCount, $r->exception, $r->firstIn]);
    }

    public function test_single_punch_is_a_missing_punch(): void
    {
        $r = $this->calc(['08:02']);
        $this->assertSame([DayType::NONE, 0.0, 1, TimecardException::MISSING_PUNCH], [$r->dayType, $r->dayValue, $r->punchCount, $r->exception]);
        $this->assertSame('08:02:00', $r->firstIn->format('H:i:s'));
        $this->assertNull($r->lastOut);
    }

    public function test_two_punches_within_a_minute_are_a_missing_punch(): void
    {
        $this->assertSame(TimecardException::MISSING_PUNCH, $this->calc(['08:02:00', '08:02:40'])->exception);
        $this->assertNull($this->calc(['08:02:00', '08:03:00', '17:00'])->exception);
    }

    public function test_duplicates_and_order_do_not_matter_and_middle_punches_are_ignored(): void
    {
        $r = $this->calc(['17:10', '12:00', '08:00', '12:45', '08:00']);
        $this->assertSame(['08:00:00', '17:10:00', 4, DayType::FULL], [$r->firstIn->format('H:i:s'), $r->lastOut->format('H:i:s'), $r->punchCount, $r->dayType]);
    }

    public static function dayTypeProvider(): array
    {
        return [
            ['07:55', '17:05', DayType::FULL, 1.0, false, null],
            ['10:00:59', '17:00:00', DayType::FULL, 1.0, false, null],
            ['10:01:00', '17:30', DayType::HALF, 0.5, false, null],
            ['08:00', '16:59:59', DayType::FULL, 1.0, true, null],
            ['08:00', '15:00:00', DayType::FULL, 1.0, true, null],
            ['08:00', '14:59:59', DayType::HALF, 0.5, false, null],
            ['08:00', '12:30:00', DayType::HALF, 0.5, false, null],
            ['08:00', '12:29:59', DayType::NONE, 0.0, false, TimecardException::SHORT_PRESENCE],
            ['10:30', '15:00', DayType::HALF, 0.5, false, null],
            ['10:30', '14:59', DayType::SHORT, 0.25, false, null],
            ['10:30', '12:30', DayType::SHORT, 0.25, false, null],
            ['10:30', '12:29', DayType::NONE, 0.0, false, TimecardException::SHORT_PRESENCE],
            ['13:00', '17:30', DayType::HALF, 0.5, false, null],
            ['16:00', '19:00', DayType::HALF, 0.5, false, null],
            ['08:00', '+00:28', DayType::FULL, 1.0, false, null],
        ];
    }

    #[DataProvider('dayTypeProvider')]
    public function test_day_type(string $in, string $out, DayType $type, float $value, bool $short, ?TimecardException $ex): void
    {
        $r = $this->calc([$in, $out]);
        $this->assertSame([$type, $value, $short, $ex], [$r->dayType, $r->dayValue, $r->shortLeave, $r->exception]);
    }

    public static function lateInProvider(): array
    {
        return [
            ['08:05:59', true, 0],
            ['08:06:00', true, 1],
            ['08:06:59', true, 1],
            ['08:07:00', true, 2],
            ['08:36:00', true, 31],
            ['10:00:30', true, 115],
            ['10:01:00', true, 0],
            ['08:36:00', false, 0],
        ];
    }

    #[DataProvider('lateInProvider')]
    public function test_late_in(string $in, bool $lateDeduction, int $expected): void
    {
        $r = $this->calc([$in, '17:00'], late: $lateDeduction);
        $this->assertSame($expected, $r->lateInMinutes);
    }

    public function test_late_from_comes_from_the_shift(): void
    {
        $r = $this->calc(['08:57', '17:40'], late: true, shift: [
            'start_time' => '08:20',
            'end_time' => '17:40',
            'late_from' => '08:21',
            'morning_cutoff' => '10:20',
            'half_day_from' => '12:50',
            'early_leave_from' => '15:20',
        ]);
        $this->assertSame(37, $r->lateInMinutes);
    }

    public static function earlyOutProvider(): array
    {
        return [
            ['17:00:00', true, 0],
            ['16:59:30', true, 1],
            ['16:30:00', true, 30],
            ['15:00:00', true, 120],
            ['14:59:00', true, 0],
            ['16:30:00', false, 0],
        ];
    }

    #[DataProvider('earlyOutProvider')]
    public function test_early_out(string $out, bool $lateDeduction, int $expected): void
    {
        $r = $this->calc(['08:00', $out], late: $lateDeduction);
        $this->assertSame($expected, $r->earlyOutMinutes);
    }

    public static function eveningOtProvider(): array
    {
        return [
            ['17:29', OtMode::EVENING, 0],
            ['17:30', OtMode::EVENING, 30],
            ['18:03', OtMode::EVENING, 60],
            ['19:59', OtMode::EVENING, 150],
            ['20:15', OtMode::EVENING, 180],
            ['20:30', OtMode::EVENING, 180],
            ['22:28', OtMode::EVENING, 270],
            ['+00:28', OtMode::EVENING, 390],
            ['19:00', OtMode::BOTH, 120],
            ['19:00', OtMode::MORNING, 0],
            ['19:00', OtMode::NONE, 0],
        ];
    }

    #[DataProvider('eveningOtProvider')]
    public function test_evening_ot(string $out, OtMode $mode, int $expected): void
    {
        $r = $this->calc(['08:00', $out], $mode);
        $this->assertSame($expected, $r->otEveningMinutes);
    }

    public static function morningOtProvider(): array
    {
        return [
            ['07:24', OtMode::BOTH, 30],
            ['07:31', OtMode::BOTH, 0],
            ['07:00', OtMode::MORNING, 60],
            ['06:00', OtMode::BOTH, 120],
            ['07:00', OtMode::EVENING, 0],
        ];
    }

    #[DataProvider('morningOtProvider')]
    public function test_morning_ot(string $in, OtMode $mode, int $expected): void
    {
        $r = $this->calc([$in, '17:00'], $mode);
        $this->assertSame($expected, $r->otMorningMinutes);
    }

    public function test_paid_break_is_not_subtracted(): void
    {
        $r = $this->calc(['08:00', '20:30'], OtMode::EVENING, breaks: [['start' => '20:00', 'end' => '20:30', 'paid' => true]]);
        $this->assertSame(210, $r->otEveningMinutes);
    }

    public function test_two_unpaid_breaks_are_both_subtracted(): void
    {
        $r = $this->calc(['08:30', '21:00'], OtMode::EVENING,
            breaks: [['start' => '18:00', 'end' => '18:15', 'paid' => false], ['start' => '20:00', 'end' => '20:30', 'paid' => false]],
            shift: ['start_time' => '08:30', 'end_time' => '17:30', 'late_from' => '08:36', 'morning_cutoff' => '10:30', 'half_day_from' => '12:30', 'early_leave_from' => '15:30']);
        $this->assertSame(150, $r->otEveningMinutes);
    }

    public function test_break_after_midnight_is_on_the_next_day(): void
    {
        $r = $this->calc(['08:00', '+01:30'], OtMode::EVENING, breaks: [['start' => '00:30', 'end' => '01:00', 'paid' => false]]);
        $this->assertSame(480, $r->otEveningMinutes);
    }

    public function test_overtime_is_computed_on_a_half_day_too(): void
    {
        $r = $this->calc(['13:00', '19:00'], OtMode::EVENING);
        $this->assertSame([DayType::HALF, 120], [$r->dayType, $r->otEveningMinutes]);
    }

    public function test_missing_punch_day_has_no_late_and_no_overtime(): void
    {
        $r = $this->calc(['08:30'], OtMode::BOTH, late: true);
        $this->assertSame([0, 0, 0, 0], [$r->lateInMinutes, $r->earlyOutMinutes, $r->otMorningMinutes, $r->otEveningMinutes]);
    }

    public function test_a_full_example(): void
    {
        $r = $this->calc(['07:24', '18:03'], OtMode::BOTH, late: true);
        $this->assertSame([DayType::FULL, 1.0, false, 0, 0, 30, 60, null],
            [$r->dayType, $r->dayValue, $r->shortLeave, $r->lateInMinutes, $r->earlyOutMinutes, $r->otMorningMinutes, $r->otEveningMinutes, $r->exception]);
    }
}
