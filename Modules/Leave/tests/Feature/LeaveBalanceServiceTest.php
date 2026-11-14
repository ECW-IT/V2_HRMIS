<?php

namespace Modules\Leave\Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Services\PayPeriodService;
use Modules\Leave\Database\Seeders\LeaveSeeder;
use Modules\Leave\Enums\LeavePortion;
use Modules\Leave\Exceptions\InvalidLeaveRequest;
use Modules\Leave\Models\LeaveLedgerEntry;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveBalanceService;
use Modules\Leave\Services\LeaveService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class LeaveBalanceServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private LeaveBalanceService $svc;

    private int $ml;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-12-10 09:00:00');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
        $this->seed(LeaveSeeder::class);
        $this->svc = app(LeaveBalanceService::class);
        $this->ml = (int) LeaveType::query()->where('code', 'ML')->value('id');
    }

    private function hr($e): User
    {
        return User::factory()->forCompanies([$e->company])->withRole('hr-manager')->withPermissions('approval.act')->create(['must_change_password' => false]);
    }

    private function officer($e): User
    {
        return User::factory()->forCompanies([$e->company])->withRole('hr-officer')->withPermissions('approval.act')->create(['must_change_password' => false]);
    }

    private function period($e, string $ym)
    {
        [$y, $m] = array_map('intval', explode('-', $ym));

        return app(PayPeriodService::class)->ensure($e->company, $y, $m);
    }

    public function test_grant_is_written_once_per_period(): void
    {
        $e = $this->hired('2026-01-01');
        $p = $this->period($e, '2026-12');
        $this->svc->ensureGrant($e, $this->ml, $p);
        $this->svc->ensureGrant($e, $this->ml, $p);
        $this->assertSame(1, LeaveLedgerEntry::where('kind', 'GRANT')->count());
        $this->assertSame(8.0, $this->svc->balance($e, $this->ml, $p));
    }

    public function test_balance_is_per_period_and_not_carried_forward(): void
    {
        $e = $this->hired('2026-01-01');
        $nov = $this->period($e, '2026-11');
        $dec = $this->period($e, '2026-12');
        $this->svc->adjust($e, $this->ml, $nov, -3, 'Test', $this->hr($e));
        $this->assertSame(5.0, $this->svc->balance($e, $this->ml, $nov));
        $this->assertSame(8.0, $this->svc->balance($e, $this->ml, $dec));
    }

    public function test_available_subtracts_pending_requests(): void
    {
        $e = $this->hired('2026-01-01');
        $p = $this->period($e, '2026-12');
        app(LeaveService::class)->request($e, $this->ml, '2026-12-15', LeavePortion::FULL, $this->officer($e));
        app(LeaveService::class)->request($e, $this->ml, '2026-12-16', LeavePortion::MORNING, $this->officer($e));
        $this->assertSame(8.0, $this->svc->balance($e, $this->ml, $p));
        $this->assertSame(6.5, $this->svc->available($e, $this->ml, $p));
    }

    public function test_joiner_during_the_period_gets_the_policy_on_the_hire_date(): void
    {
        $e = $this->hired('2026-12-05');
        $p = $this->period($e, '2026-12');
        $this->assertSame(8.0, $this->svc->balance($e, $this->ml, $p));
    }

    public function test_ledger_rows_cannot_be_changed_or_deleted(): void
    {
        $e = $this->hired('2026-01-01');
        $entry = $this->svc->ensureGrant($e, $this->ml, $this->period($e, '2026-12'));
        try {
            $entry->update(['days' => 1]);
            $this->fail();
        } catch (\LogicException) {
            $this->addToAssertionCount(1);
        }
        try {
            $entry->delete();
            $this->fail();
        } catch (\LogicException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_adjust_needs_leave_manage_and_an_open_period(): void
    {
        $e = $this->hired('2026-01-01');
        $p = $this->period($e, '2026-12');
        $officer = $this->officer($e);
        try {
            $this->svc->adjust($e, $this->ml, $p, -1, 'x', $officer);
            $this->fail();
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        Carbon::setTestNow('2027-01-05 09:00:00');
        $pp = app(PayPeriodService::class);
        $pp->markClosed($p, null);
        $this->expectException(InvalidLeaveRequest::class);
        $this->svc->adjust($e, $this->ml, $p, -1, 'x', $this->hr($e));
    }
}
