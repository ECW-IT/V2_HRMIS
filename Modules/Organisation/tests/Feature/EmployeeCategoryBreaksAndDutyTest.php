<?php

namespace Modules\Organisation\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organisation\Exceptions\DutyEffectiveFromRequired;
use Modules\Organisation\Models\BreakType;
use Modules\Organisation\Models\EmployeeCategoryDutySchedule;
use Modules\Organisation\Services\EmployeeCategoryService;
use Tests\TestCase;

class EmployeeCategoryBreaksAndDutyTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_writes_initial_duty_schedule_and_syncs_breaks(): void
    {
        $tea = BreakType::factory()->create(['name' => 'Tea']);
        $lunch = BreakType::factory()->create(['name' => 'Lunch']);

        $category = app(EmployeeCategoryService::class)->create([
            'code' => 'FAC',
            'name' => 'Factory',
            'on_duty_time' => '08:00',
            'off_duty_time' => '17:00',
            'breaks' => [
                [
                    'break_type_id' => $tea->id,
                    'start_time' => '10:00',
                    'end_time' => '10:15',
                    'duration_minutes' => 15,
                ],
                [
                    'break_type_id' => $lunch->id,
                    'start_time' => '12:00',
                    'end_time' => '12:30',
                    'duration_minutes' => 30,
                ],
            ],
        ]);

        $this->assertSame(2, $category->breaks()->count());
        $this->assertSame(1, $category->dutySchedules()->count());
        $this->assertNull($category->dutySchedules()->sole()->effective_to);
        $this->assertSame('08:00:00', substr((string) $category->dutySchedules()->sole()->on_duty_time, 0, 8));
    }

    public function test_break_sync_updates_and_removes_rows(): void
    {
        $tea = BreakType::factory()->create(['name' => 'Tea']);
        $lunch = BreakType::factory()->create(['name' => 'Lunch']);
        $svc = app(EmployeeCategoryService::class);

        $category = $svc->create([
            'code' => 'FAC',
            'name' => 'Factory',
            'breaks' => [
                [
                    'break_type_id' => $tea->id,
                    'start_time' => '10:00',
                    'end_time' => '10:15',
                    'duration_minutes' => 15,
                ],
            ],
        ]);

        $svc->update($category, [
            'breaks' => [
                [
                    'break_type_id' => $tea->id,
                    'start_time' => '10:00',
                    'end_time' => '10:20',
                    'duration_minutes' => 20,
                ],
                [
                    'break_type_id' => $lunch->id,
                    'start_time' => '12:00',
                    'end_time' => '12:45',
                    'duration_minutes' => 45,
                ],
            ],
        ]);

        $category->refresh();
        $this->assertSame(2, $category->breaks()->count());
        $this->assertSame(20, $category->breaks()->where('break_type_id', $tea->id)->value('duration_minutes'));

        $svc->update($category, [
            'breaks' => [
                [
                    'break_type_id' => $lunch->id,
                    'start_time' => '12:00',
                    'end_time' => '12:45',
                    'duration_minutes' => 45,
                ],
            ],
        ]);

        $this->assertSame(1, $category->breaks()->count());
        $this->assertSame($lunch->id, $category->breaks()->sole()->break_type_id);
    }

    public function test_duty_change_requires_effective_from_and_creates_history(): void
    {
        $svc = app(EmployeeCategoryService::class);
        $category = $svc->create([
            'code' => 'FAC',
            'name' => 'Factory',
            'on_duty_time' => '08:00',
            'off_duty_time' => '17:00',
        ]);

        $this->expectException(DutyEffectiveFromRequired::class);
        $svc->update($category, [
            'on_duty_time' => '09:00',
            'off_duty_time' => '17:00',
        ]);
    }

    public function test_duty_change_closes_prior_schedule_and_effective_on_resolves(): void
    {
        $svc = app(EmployeeCategoryService::class);
        $category = $svc->create([
            'code' => 'FAC',
            'name' => 'Factory',
            'on_duty_time' => '08:00',
            'off_duty_time' => '17:00',
        ]);

        $from = CarbonImmutable::now(config('app.timezone'))->addDay()->toDateString();

        $svc->update($category->fresh(), [
            'on_duty_time' => '09:00',
            'off_duty_time' => '18:00',
            'duty_effective_from' => $from,
        ]);

        $category->refresh();
        $this->assertSame(2, $category->dutySchedules()->count());

        $prior = $category->dutySchedules()->orderBy('effective_from')->first();
        $this->assertSame(
            CarbonImmutable::createFromFormat('Y-m-d', $from)->subDay()->toDateString(),
            $prior->effective_to->toDateString(),
        );

        $current = EmployeeCategoryDutySchedule::query()
            ->where('employee_category_id', $category->id)
            ->effectiveOn($from)
            ->first();

        $this->assertNotNull($current);
        $this->assertSame('09:00:00', substr((string) $current->on_duty_time, 0, 8));
        $this->assertSame('09:00:00', substr((string) $category->on_duty_time, 0, 8));
    }
}
