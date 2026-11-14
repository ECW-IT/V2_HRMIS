<?php

namespace Modules\Leave\Tests\Feature;

use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\Leave\Database\Seeders\LeaveSeeder;
use Modules\Leave\Models\LeavePolicy;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeavePolicyService;
use Modules\Organisation\Models\Grade;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class LeavePolicyServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private LeavePolicyService $svc;

    private int $ml;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-12-10 09:00:00');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(LeaveSeeder::class);
        $this->svc = app(LeavePolicyService::class);
        $this->ml = (int) LeaveType::query()->where('code', 'ML')->value('id');
    }

    public function test_general_policy_applies_to_everyone(): void
    {
        $this->assertSame('8.0', $this->svc->resolve($this->hired('2026-01-01'), $this->ml, '2026-12-01')->days_per_period);
    }

    public function test_most_specific_policy_wins(): void
    {
        $e = $this->hired('2026-01-01');
        $grade = $e->currentAssignment()->grade_id;
        LeavePolicy::factory()->create(['leave_type_id' => $this->ml, 'grade_id' => $grade, 'days_per_period' => 6, 'effective_from' => '2026-01-01']);
        LeavePolicy::factory()->create(['leave_type_id' => $this->ml, 'grade_id' => $grade, 'company_id' => $e->company_id, 'days_per_period' => 5, 'effective_from' => '2026-01-01']);
        LeavePolicy::factory()->create(['leave_type_id' => $this->ml, 'grade_id' => Grade::factory()->create()->id, 'days_per_period' => 2, 'effective_from' => '2026-01-01']);
        $this->assertSame('5.0', $this->svc->resolve($e, $this->ml, '2026-12-01')->days_per_period);
    }

    public function test_policy_dates_are_respected(): void
    {
        LeavePolicy::query()->update(['effective_to' => '2026-11-30']);
        LeavePolicy::factory()->create(['leave_type_id' => $this->ml, 'days_per_period' => 6, 'effective_from' => '2026-12-01']);
        $e = $this->hired('2026-01-01');
        $this->assertSame('8.0', $this->svc->resolve($e, $this->ml, '2026-11-30')->days_per_period);
        $this->assertSame('6.0', $this->svc->resolve($e, $this->ml, '2026-12-01')->days_per_period);
    }

    public function test_employment_type_policy(): void
    {
        LeavePolicy::factory()->create([
            'leave_type_id' => $this->ml,
            'employment_type' => EmploymentType::INTERN->value,
            'days_per_period' => 2,
            'effective_from' => '2026-01-01',
        ]);

        $intern = $this->hired('2026-01-01', emp: ['type' => EmploymentType::INTERN]);
        $permanent = $this->hired('2026-01-01');

        $this->assertSame('2.0', $this->svc->resolve($intern, $this->ml, '2026-12-01')->days_per_period);
        $this->assertSame('8.0', $this->svc->resolve($permanent, $this->ml, '2026-12-01')->days_per_period);
    }

    public function test_no_matching_policy_returns_null(): void
    {
        LeavePolicy::query()->delete();
        $this->assertNull($this->svc->resolve($this->hired('2026-01-01'), $this->ml, '2026-12-01'));
    }
}
