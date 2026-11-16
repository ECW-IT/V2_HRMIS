<?php

namespace Modules\Payroll\Tests\Feature;

use App\Models\User;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Services\BankAccountService;
use Modules\Organisation\Models\Company;
use Modules\Payroll\Database\Seeders\LoanTypeSeeder;
use Modules\Payroll\Enums\ExtraDayMethod;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Exceptions\InvalidPayRunAction;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunEmployee;
use Modules\Payroll\Models\PayRunLine;
use Modules\Payroll\Models\PayRunOverride;
use Modules\Payroll\Services\AdvanceService;
use Modules\Payroll\Services\LoanService;
use Modules\Payroll\Services\PayEntryService;
use Modules\Payroll\Services\PayRuleService;
use Modules\Payroll\Services\PayRunOverrideService;
use Modules\Payroll\Services\PayRunService;
use Modules\Workflow\Services\ApprovalService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class PayRunOverrideTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private Company $company;

    private User $officer;

    private User $approver;

    private Employment $standard;

    private Employment $full;

    private PayPeriod $period;

    private PayRun $run;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
        $this->seed(LoanTypeSeeder::class);
        $this->travelTo('2026-12-15');

        $this->company = $this->company();
        $this->officer = $this->userWith('payroll-officer');
        $this->approver = $this->userWith('hr-manager');

        // A full-month employee (net 55,200) next to the standard example (net 57,217).
        $this->full = $this->paid('2026-01-01', $this->company);
        $this->hourly($this->full);
        $this->standard = $this->standardEmployee();

        $this->period = $this->closedPeriod($this->company);
        $this->summary($this->full, $this->period);
        $this->summary($this->standard, $this->period, [
            'credited_days' => 24,
            'late_minutes_chargeable' => 30,
            'ot_normal_minutes' => 600,
        ]);

        $runs = app(PayRunService::class);
        $this->run = $runs->calculate($runs->open($this->period, $this->officer), $this->officer)->fresh();
    }

    private function userWith(string $role, array $attributes = []): User
    {
        return User::factory()
            ->forCompanies([$this->company])
            ->withRole($role)
            ->withPermissions('approval.act')
            ->create(array_merge(['must_change_password' => false], $attributes));
    }

    private function hourly(Employment $employment): void
    {
        $this->actingAs($this->officer);
        Gate::authorize('payroll.config');
        $grade = $employment->assignmentOn('2026-12-31')->grade;
        app(PayRuleService::class)->setExtraDayMethod($grade, ExtraDayMethod::HOURLY, '2026-01-01');
    }

    private function standardEmployee(): Employment
    {
        $e = $this->paid('2026-01-01', $this->company, ['attendance_tier_code' => 'T1'], [
            'PERF' => '10000.00',
            'FUEL' => '5000.00',
        ]);
        $this->hourly($e);
        app(BankAccountService::class)->set($e, $this->account(['effective_from' => '2026-01-01']));

        $period = app(PayPeriodService::class)->ensure($this->company, 2026, 12);
        app(PayEntryService::class)->add($e, $period, ['component_code' => 'MEALS', 'amount' => '3000.00'], $this->officer);

        $advance = app(AdvanceService::class)->request($e, $period, '10000.00', 'Urgent need', $this->officer);
        app(ApprovalService::class)->approve($advance->approvalRequest, $this->approver);
        app(AdvanceService::class)->markPaid($advance->fresh(), '2026-12-10', null, $this->officer);

        $loan = app(LoanService::class)->request($e, [
            'loan_type_code' => 'STAFF',
            'amount' => '5000.00',
            'instalments' => 1,
            'first_month' => '2026-12-01',
            'reason' => 'Test loan for pay run',
        ], $this->officer);
        app(ApprovalService::class)->approve($loan->approvalRequest, $this->approver);
        app(LoanService::class)->markPaid($loan->fresh(), '2026-12-01', null, $this->officer);

        return $e->fresh();
    }

    private function row(Employment $e): PayRunEmployee
    {
        return PayRunEmployee::query()
            ->where('pay_run_id', $this->run->id)
            ->where('employment_id', $e->id)
            ->firstOrFail();
    }

    private function line(Employment $e, string $code): ?PayRunLine
    {
        return $this->row($e)->lines()->where('code', $code)->first();
    }

    private function overrides(): PayRunOverrideService
    {
        return app(PayRunOverrideService::class);
    }

    /** @param  class-string<\Throwable>  $class */
    private function assertRefused(string $class, callable $action): void
    {
        try {
            $action();
        } catch (\Throwable $e) {
            $this->assertInstanceOf($class, $e, 'Unexpected exception: '.$e::class.': '.$e->getMessage());

            return;
        }

        $this->fail("Expected {$class} to be thrown.");
    }

    public function test_override_recalculates_the_employee_and_the_run_totals(): void
    {
        $this->assertSame('57217.00', (string) $this->row($this->standard)->net_pay);
        $this->assertSame('112417.00', (string) $this->run->net_pay);
        $this->assertFalse((bool) $this->line($this->standard, 'FUEL')->is_override);

        $override = $this->overrides()->set($this->run, $this->standard, 'FUEL', '2000.00', 'Fuel card was withdrawn', $this->officer);

        $this->assertSame('2000.00', (string) $override->amount);
        $this->assertSame('Fuel card was withdrawn', $override->reason);
        $this->assertSame($this->officer->id, (int) $override->set_by);
        $this->assertNull($override->cancelled_at);

        $row = $this->row($this->standard);
        $this->assertSame('54217.00', (string) $row->net_pay);

        $fuel = $this->line($this->standard, 'FUEL');
        $this->assertSame('2000.00', (string) $fuel->amount);
        $this->assertTrue((bool) $fuel->is_override);

        // The other employee is untouched and the run totals follow.
        $this->assertSame('55200.00', (string) $this->row($this->full)->net_pay);
        $run = $this->run->fresh();
        $this->assertSame('109417.00', (string) $run->net_pay);
        $this->assertSame(2, $run->employee_count);
        $this->assertSame(0, $run->error_count);
        $this->assertSame(PayRunStatus::CALCULATED, $run->status);
    }

    public function test_cancelling_an_override_restores_the_calculated_figure(): void
    {
        $override = $this->overrides()->set($this->run, $this->standard, 'FUEL', '2000.00', 'Fuel card was withdrawn', $this->officer);
        $this->assertSame('54217.00', (string) $this->row($this->standard)->net_pay);

        $this->overrides()->cancel($override, $this->officer);

        $this->assertNotNull($override->fresh()->cancelled_at);
        $this->assertSame('57217.00', (string) $this->row($this->standard)->net_pay);

        $fuel = $this->line($this->standard, 'FUEL');
        $this->assertSame('5000.00', (string) $fuel->amount);
        $this->assertFalse((bool) $fuel->is_override);
        $this->assertSame('112417.00', (string) $this->run->fresh()->net_pay);

        // Cancelling twice is refused.
        $this->assertRefused(InvalidPayRunAction::class, fn () => $this->overrides()->cancel($override->fresh(), $this->officer));
    }

    public function test_setting_an_override_again_cancels_the_old_one(): void
    {
        $first = $this->overrides()->set($this->run, $this->standard, 'FUEL', '2000.00', 'Fuel card was withdrawn', $this->officer);
        $second = $this->overrides()->set($this->run, $this->standard, 'FUEL', '3000.00', 'Fuel card was reissued', $this->officer);

        $this->assertNotNull($first->fresh()->cancelled_at);
        $this->assertNull($second->fresh()->cancelled_at);
        $this->assertSame(
            1,
            PayRunOverride::query()->where('pay_run_id', $this->run->id)->whereNull('cancelled_at')->count(),
        );
        $this->assertSame('3000.00', (string) $this->line($this->standard, 'FUEL')->amount);
        $this->assertSame('55217.00', (string) $this->row($this->standard)->net_pay);
    }

    public function test_override_needs_a_reason_permission_and_an_open_run(): void
    {
        // A reason of at least 10 characters.
        $this->assertRefused(InvalidPayRunAction::class, fn () => $this->overrides()->set($this->run, $this->standard, 'FUEL', '2000.00', '.', $this->officer));
        $this->assertRefused(InvalidPayRunAction::class, fn () => $this->overrides()->set($this->run, $this->standard, 'FUEL', '2000.00', 'too short', $this->officer));

        // payrun.override: hr-manager does not have it.
        $this->assertRefused(AuthorizationException::class, fn () => $this->overrides()->set($this->run, $this->standard, 'FUEL', '2000.00', 'Fuel card was withdrawn', $this->approver));

        // The user must belong to the run's company.
        $outsider = User::factory()->forCompanies([$this->company()])->withRole('payroll-officer')->create(['must_change_password' => false]);
        $this->assertRefused(AuthorizationException::class, fn () => $this->overrides()->set($this->run, $this->standard, 'FUEL', '2000.00', 'Fuel card was withdrawn', $outsider));

        // The employee must be in the run.
        $stranger = $this->paid('2026-01-01', $this->company);
        $this->assertRefused(InvalidPayRunAction::class, fn () => $this->overrides()->set($this->run, $stranger, 'FUEL', '2000.00', 'Fuel card was withdrawn', $this->officer));

        $this->assertSame(0, PayRunOverride::query()->count());
        $this->assertSame('57217.00', (string) $this->row($this->standard)->net_pay);

        // An approved run is closed to overrides, and so is cancelling one.
        $override = $this->overrides()->set($this->run, $this->standard, 'FUEL', '2000.00', 'Fuel card was withdrawn', $this->officer);
        app(PayRunService::class)->approve($this->run->fresh(), $this->approver);

        $this->assertRefused(InvalidPayRunAction::class, fn () => $this->overrides()->set($this->run->fresh(), $this->standard, 'MOBILE', '1000.00', 'Mobile allowance added', $this->officer));
        $this->assertRefused(InvalidPayRunAction::class, fn () => $this->overrides()->cancel($override->fresh(), $this->officer));
        $this->assertNull($override->fresh()->cancelled_at);
        $this->assertSame(1, PayRunOverride::query()->count());

        // Sending the run back opens it again.
        app(PayRunService::class)->sendBack($this->run->fresh(), $this->approver, 'Need to change an override');
        $this->overrides()->cancel($override->fresh(), $this->officer);
        $this->assertNotNull($override->fresh()->cancelled_at);
        $this->assertSame('57217.00', (string) $this->row($this->standard)->net_pay);
    }

    public function test_override_of_a_ledger_component_is_refused(): void
    {
        foreach (['ADVANCE', 'LOAN', 'CF', 'EPF_ER'] as $code) {
            $this->assertRefused(
                InvalidPayRunAction::class,
                fn () => $this->overrides()->set($this->run, $this->standard, $code, '1.00', 'Trying to change a ledger line', $this->officer),
            );
        }

        $this->assertRefused(
            InvalidPayRunAction::class,
            fn () => $this->overrides()->set($this->run, $this->standard, 'NO_SUCH_CODE', '1.00', 'There is no such component', $this->officer),
        );

        $this->assertSame(0, PayRunOverride::query()->count());
        $this->assertSame('10000.00', (string) $this->line($this->standard, 'ADVANCE')->amount);
        $this->assertSame('57217.00', (string) $this->row($this->standard)->net_pay);
    }

    public function test_nobody_overrides_their_own_pay(): void
    {
        $self = $this->userWith('payroll-officer', ['person_id' => $this->standard->person_id]);

        $this->assertRefused(
            AuthorizationException::class,
            fn () => $this->overrides()->set($this->run, $this->standard, 'FUEL', '2000.00', 'Giving myself less fuel pay', $self),
        );
        $this->assertSame(0, PayRunOverride::query()->count());
        $this->assertSame('57217.00', (string) $this->row($this->standard)->net_pay);

        // The same user may override somebody else.
        $other = $this->overrides()->set($this->run, $this->full, 'SPECIAL', '1500.00', 'Special allowance for December', $self);
        $this->assertNull($other->cancelled_at);
        $this->assertSame('1500.00', (string) $this->line($this->full, 'SPECIAL')->amount);
    }

    public function test_overrides_survive_recalculation_of_the_run(): void
    {
        $this->overrides()->set($this->run, $this->standard, 'FUEL', '2000.00', 'Fuel card was withdrawn', $this->officer);

        $runs = app(PayRunService::class);
        $runs->calculate($this->run->fresh(), $this->officer);

        $fuel = $this->line($this->standard, 'FUEL');
        $this->assertSame('2000.00', (string) $fuel->amount);
        $this->assertTrue((bool) $fuel->is_override);
        $this->assertSame('54217.00', (string) $this->row($this->standard)->net_pay);
        $this->assertSame('109417.00', (string) $this->run->fresh()->net_pay);

        // Recalculating one employee keeps it as well.
        $runs->recalculateEmployee($this->run->fresh(), $this->standard->id, $this->officer);
        $this->assertSame('54217.00', (string) $this->row($this->standard)->net_pay);
        $this->assertSame(1, PayRunOverride::query()->where('pay_run_id', $this->run->id)->whereNull('cancelled_at')->count());
    }
}
