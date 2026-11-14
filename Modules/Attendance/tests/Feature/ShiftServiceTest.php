<?php

namespace Modules\Attendance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Database\Seeders\ShiftSeeder;
use Modules\Attendance\Exceptions\InvalidShift;
use Modules\Attendance\Models\Shift;
use Modules\Attendance\Services\ShiftService;
use Modules\Attendance\Services\TimeProfileService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class ShiftServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private ShiftService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = app(ShiftService::class);
    }

    /** @return array<string, string> */
    private function shiftData(): array
    {
        return [
            'code' => 'X',
            'name' => 'X',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'late_from' => '08:06',
            'morning_cutoff' => '10:00',
            'half_day_from' => '12:30',
            'early_leave_from' => '15:00',
        ];
    }

    public function test_times_must_be_in_order(): void
    {
        $ok = $this->shiftData();
        $this->assertNotNull($this->svc->create($ok));
        foreach ([['late_from' => '07:59'], ['morning_cutoff' => '08:05'], ['half_day_from' => '10:00'], ['early_leave_from' => '12:30'], ['end_time' => '15:00']] as $i => $bad) {
            try {
                $this->svc->create(array_merge($ok, ['code' => "B$i"], $bad));
                $this->fail(json_encode($bad));
            } catch (InvalidShift) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_break_end_must_be_after_start(): void
    {
        $this->expectException(InvalidShift::class);
        $this->svc->create($this->shiftData(), [['name' => 'Dinner', 'start_time' => '20:30', 'end_time' => '20:00', 'is_paid' => false]]);
    }

    public function test_shift_in_use_cannot_be_deactivated(): void
    {
        $e = $this->profiled();
        $this->expectException(InvalidShift::class);
        $this->svc->deactivate(app(TimeProfileService::class)->on($e, '2026-06-01')->shift);
    }

    public function test_seeder_creates_four_shifts_with_breaks_and_can_rerun(): void
    {
        $this->seed(ShiftSeeder::class);
        $this->seed(ShiftSeeder::class);
        $this->assertSame(['D0700', 'D0730', 'D0800', 'D0830'], Shift::orderBy('code')->pluck('code')->all());
        $this->assertSame(2, Shift::where('code', 'D0830')->sole()->breaks()->count());
        $this->assertSame('08:36:00', Shift::where('code', 'D0830')->value('late_from'));
    }

    public function test_at_builds_a_datetime_on_the_work_date(): void
    {
        $this->assertSame('2026-12-01 17:00:00', $this->shift()->at('2026-12-01', 'end_time')->format('Y-m-d H:i:s'));
    }
}
