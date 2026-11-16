<?php

namespace Modules\LegacyImport\Tests\Feature\Pay;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\LegacyImport\Models\StgIssue;
use Modules\LegacyImport\Tests\Support\LegacyFixtures;
use Modules\Organisation\Models\Company;
use Modules\Payroll\Database\Seeders\LoanTypeSeeder;
use Modules\Payroll\Database\Seeders\PayComponentSeeder;
use Modules\Payroll\Database\Seeders\PayRuleSeeder;
use Modules\Payroll\Enums\AdvanceStatus;
use Modules\Payroll\Enums\InstalmentStatus;
use Modules\Payroll\Enums\LoanStatus;
use Modules\Payroll\Enums\OtRateMode;
use Modules\Payroll\Models\EmployeeCompensation;
use Modules\Payroll\Models\EmployeeComponent;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\SalaryAdvance;
use Modules\Payroll\Services\CompensationService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class PayLoadTest extends TestCase
{
    use HrFixtures;
    use LegacyFixtures;
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PayComponentSeeder::class);
        $this->seed(PayRuleSeeder::class);
        $this->seed(LoanTypeSeeder::class);
        $this->setUpLegacyFixtures();
        $this->company = Company::query()->where('code', 'CO1')->firstOrFail();
        $this->user = User::factory()->allCompanies()->withRole('payroll-officer')->create([
            'must_change_password' => false,
        ]);
        $this->actingAs($this->user);
        $this->travelTo('2027-01-15');
    }

    public function test_loads_compensation_from_the_profile(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 001']);
        $profileId = $this->legacyProfile([
            'emp_no' => 'ECW 001',
            'branch_id' => 1,
            'basic_salary' => 60000,
            'ot_rate_mode' => 'calculated',
            'attendance_type' => 'type1',
            'status' => 1,
        ]);
        $this->legacyAdjustmentType(['id' => 3, 'name' => 'Attendance Allowance']);
        $this->legacyEmployeeAdjustment($profileId, 3, 1000);

        $this->runPayImport('2027-01-01');

        $comp = app(CompensationService::class)->on($e->fresh(), '2027-01-01');
        $this->assertNotNull($comp);
        $this->assertSame('60000.00', (string) $comp->basic_salary);
        $this->assertSame(OtRateMode::CALCULATED, $comp->ot_rate_mode);
        $this->assertSame('T1', $comp->attendance_tier_code);
    }

    public function test_fixed_ot_mode_takes_the_rate_from_the_ot_rate_adjustment(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 002']);
        $profileId = $this->legacyProfile([
            'emp_no' => 'ECW 002',
            'branch_id' => 1,
            'basic_salary' => 50000,
            'ot_rate_mode' => 'fixed',
            'status' => 1,
        ]);
        $this->legacyAdjustmentType(['id' => 2, 'name' => 'OT Rate']);
        $this->legacyEmployeeAdjustment($profileId, 2, 375);

        $this->runPayImport('2027-01-01');

        $comp = app(CompensationService::class)->on($e->fresh(), '2027-01-01');
        $this->assertSame(OtRateMode::FIXED, $comp->ot_rate_mode);
        $this->assertSame('375.00', (string) $comp->ot_rate);
    }

    public function test_tier_is_null_without_the_attendance_adjustment(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 003']);
        $this->legacyProfile([
            'emp_no' => 'ECW 003',
            'branch_id' => 1,
            'basic_salary' => 60000,
            'ot_rate_mode' => 'calculated',
            'attendance_type' => 'type1',
            'status' => 1,
        ]);

        $this->runPayImport('2027-01-01');

        $comp = app(CompensationService::class)->on($e->fresh(), '2027-01-01');
        $this->assertNull($comp->attendance_tier_code);
    }

    public function test_mapped_adjustments_become_recurring_components_and_zero_amounts_are_skipped(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 004']);
        $profileId = $this->legacyProfile([
            'emp_no' => 'ECW 004',
            'branch_id' => 1,
            'basic_salary' => 60000,
            'status' => 1,
        ]);
        $this->legacyAdjustmentType(['id' => 1, 'name' => 'Fuel']);
        $this->legacyEmployeeAdjustment($profileId, 1, 2000);
        $this->legacyEmployeeAdjustment($profileId, 1, 0);

        $this->runPayImport('2027-01-01');

        $fuel = PayComponent::query()->where('code', 'FUEL')->firstOrFail();
        $rows = EmployeeComponent::query()
            ->where('employment_id', $e->id)
            ->where('pay_component_id', $fuel->id)
            ->get();
        $this->assertCount(1, $rows);
        $this->assertSame('2000.00', (string) $rows->first()->amount);
    }

    public function test_hostel_flag_becomes_a_hostel_component_at_the_latest_rate(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 005']);
        $this->legacyAdjustmentType(['id' => 4, 'name' => 'Hostel']);
        $this->legacyAdjustmentRate(4, ['amount' => 3500, 'start_date' => '2026-06-01']);
        $this->legacyAdjustmentRate(4, ['amount' => 3000, 'start_date' => '2026-01-01']);
        $this->legacyProfile([
            'emp_no' => 'ECW 005',
            'branch_id' => 1,
            'basic_salary' => 60000,
            'hostal' => 1,
            'status' => 1,
        ]);

        $this->runPayImport('2027-01-01');

        $hostel = PayComponent::query()->where('code', 'HOSTEL')->firstOrFail();
        $row = EmployeeComponent::query()
            ->where('employment_id', $e->id)
            ->where('pay_component_id', $hostel->id)
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('3500.00', (string) $row->amount);
    }

    public function test_open_loan_is_loaded_with_only_its_unpaid_instalments(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 006']);
        $profileId = $this->legacyProfile([
            'emp_no' => 'ECW 006',
            'branch_id' => 1,
            'basic_salary' => 60000,
            'status' => 1,
        ]);
        $this->legacyLoan($profileId, [
            'approved_amount' => 60000,
            'approved_installments' => 6,
            'if_approved' => 1,
            'status' => 1,
            'paid' => 1,
        ], [
            ['month' => '2026-07-01', 'amount' => 10000, 'paid' => 1],
            ['month' => '2026-08-01', 'amount' => 10000, 'paid' => 1],
            ['month' => '2026-09-01', 'amount' => 10000, 'paid' => 1],
            ['month' => '2027-01-01', 'amount' => 10000, 'paid' => 0],
            ['month' => '2027-02-01', 'amount' => 10000, 'paid' => 0],
            ['month' => '2027-03-01', 'amount' => 10000, 'paid' => 0],
        ]);

        $this->runPayImport('2027-01-01');

        $loan = Loan::query()->where('employment_id', $e->id)->first();
        $this->assertNotNull($loan);
        $this->assertSame(LoanStatus::ACTIVE, $loan->status);
        $this->assertSame('30000.00', (string) $loan->amount);
        $due = $loan->instalmentRows()->where('status', InstalmentStatus::DUE)->orderBy('due_month')->get();
        $this->assertCount(3, $due);
    }

    public function test_overdue_instalment_is_moved_to_the_from_month_and_reported(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 007']);
        $profileId = $this->legacyProfile([
            'emp_no' => 'ECW 007',
            'branch_id' => 1,
            'basic_salary' => 60000,
            'status' => 1,
        ]);
        $this->legacyLoan($profileId, [
            'approved_amount' => 10000,
            'if_approved' => 1,
            'status' => 1,
            'paid' => 1,
        ], [
            ['month' => '2026-06-01', 'amount' => 10000, 'paid' => 0],
        ]);

        $this->runPayImport('2027-01-01');

        $this->assertTrue(StgIssue::query()->where('code', 'PAY010')->exists());
        $loan = Loan::query()->where('employment_id', $e->id)->firstOrFail();
        $this->assertSame('2027-01-01', $loan->instalmentRows()->first()->due_month->toDateString());
    }

    public function test_paid_unrecovered_advance_is_loaded_for_the_first_period(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 008']);
        $profileId = $this->legacyProfile([
            'emp_no' => 'ECW 008',
            'branch_id' => 1,
            'basic_salary' => 60000,
            'status' => 1,
        ]);
        $this->legacyAdvance($profileId, [
            'amount' => 12000,
            'is_paid' => 1,
            'converted_to_loan_id' => null,
        ], [
            ['month' => '2027-01-01', 'amount' => 12000, 'paid' => 0],
        ]);

        $this->runPayImport('2027-01-01');

        $advance = SalaryAdvance::query()->where('employment_id', $e->id)->first();
        $this->assertNotNull($advance);
        $this->assertSame(AdvanceStatus::PAID, $advance->status);
        $this->assertSame('12000.00', (string) $advance->amount);
        $period = app(PayPeriodService::class)->ensure($this->company, 2027, 1);
        $this->assertSame($period->id, $advance->pay_period_id);
    }

    public function test_intern_is_loaded_with_zero_basic(): void
    {
        $e = $this->hired('2026-01-01', $this->company, ['type' => EmploymentType::INTERN->value]);
        $e->update(['legacy_employee_no' => 'ECW 009']);
        $this->legacyProfile([
            'emp_no' => 'ECW 009',
            'branch_id' => 1,
            'basic_salary' => 25000,
            'status' => 1,
        ]);

        $this->runPayImport('2027-01-01');

        $comp = app(CompensationService::class)->on($e->fresh(), '2027-01-01');
        $this->assertSame('0.00', (string) $comp->basic_salary);
    }

    public function test_blocked_employee_is_skipped_and_the_rest_load(): void
    {
        $ok = $this->hired('2026-01-01', $this->company);
        $ok->update(['legacy_employee_no' => 'ECW 010']);
        $bad = $this->hired('2026-01-01', $this->company);
        $bad->update(['legacy_employee_no' => 'ECW 011']);
        $this->legacyProfile(['emp_no' => 'ECW 010', 'branch_id' => 1, 'basic_salary' => 60000, 'status' => 1]);
        $this->legacyProfile(['emp_no' => 'ECW 011', 'branch_id' => 1, 'basic_salary' => 0, 'status' => 1]);

        $this->runPayImport('2027-01-01');

        $this->assertNotNull(app(CompensationService::class)->on($ok->fresh(), '2027-01-01'));
        $this->assertNull(app(CompensationService::class)->on($bad->fresh(), '2027-01-01'));
    }

    public function test_dry_run_changes_nothing_and_still_writes_the_file(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 012']);
        $this->legacyProfile(['emp_no' => 'ECW 012', 'branch_id' => 1, 'basic_salary' => 60000, 'status' => 1]);

        $this->artisan('hrmis:import:pay-stage', ['--company' => 'CO1'])->assertSuccessful();
        $this->artisan('hrmis:import:pay-validate', ['--company' => 'CO1'])->assertSuccessful();
        $this->artisan('hrmis:import:pay-load', [
            '--company' => 'CO1',
            '--from' => '2027-01-01',
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, EmployeeCompensation::query()->where('employment_id', $e->id)->count());
        $this->assertNotEmpty(glob(storage_path('app/private/import/pay-reconciliation-CO1-*.xlsx')));
    }

    public function test_load_refuses_a_company_that_already_has_compensation(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 013']);
        app(CompensationService::class)->set($e, '2026-01-01', [
            'basic_salary' => '50000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
        ]);
        $this->legacyProfile(['emp_no' => 'ECW 013', 'branch_id' => 1, 'basic_salary' => 60000, 'status' => 1]);

        $this->artisan('hrmis:import:pay-stage', ['--company' => 'CO1']);
        $this->artisan('hrmis:import:pay-validate', ['--company' => 'CO1']);
        $this->artisan('hrmis:import:pay-load', [
            '--company' => 'CO1',
            '--from' => '2027-01-01',
        ])->expectsOutputToContain('already has compensation')->assertFailed();
    }

    public function test_nothing_is_written_to_the_legacy_database(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 014']);
        $this->legacyProfile(['emp_no' => 'ECW 014', 'branch_id' => 1, 'basic_salary' => 60000, 'status' => 1]);

        DB::connection('legacy_payroll')->enableQueryLog();
        $this->runPayImport('2027-01-01');
        foreach (DB::connection('legacy_payroll')->getQueryLog() as $q) {
            $this->assertStringStartsWith('select', strtolower(ltrim($q['query'])));
        }
    }

    private function runPayImport(string $from): void
    {
        $this->artisan('hrmis:import:pay-stage', ['--company' => 'CO1'])->assertSuccessful();
        $this->artisan('hrmis:import:pay-validate', ['--company' => 'CO1'])->assertSuccessful();
        $this->artisan('hrmis:import:pay-load', [
            '--company' => 'CO1',
            '--from' => $from,
        ])->assertSuccessful();
    }
}
