<?php

namespace Modules\LegacyImport\Tests\Feature\Pay;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LegacyImport\Models\StgIssue;
use Modules\LegacyImport\Tests\Support\LegacyFixtures;
use Modules\Organisation\Models\Company;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class PayValidateTest extends TestCase
{
    use HrFixtures;
    use LegacyFixtures;
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->setUpLegacyFixtures();
        $this->company = Company::query()->where('code', 'CO1')->firstOrFail();
    }

    public function test_pay001_active_profile_without_employment(): void
    {
        $this->legacyProfile(['emp_no' => 'ECW 999', 'branch_id' => 1, 'status' => 1]);
        $this->artisan('hrmis:import:pay-stage', ['--company' => 'CO1'])->assertSuccessful();
        $this->artisan('hrmis:import:pay-validate', ['--company' => 'CO1'])->assertSuccessful();
        $this->assertTrue(StgIssue::query()->where('code', 'PAY001')->exists());
    }

    public function test_pay002_active_employment_without_profile(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 888']);
        $this->artisan('hrmis:import:pay-stage', ['--company' => 'CO1']);
        $this->artisan('hrmis:import:pay-validate', ['--company' => 'CO1'])->assertSuccessful();
        $this->assertTrue(StgIssue::query()->where('code', 'PAY002')->exists());
    }

    public function test_pay003_basic_missing_for_non_intern(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 301']);
        $this->legacyProfile(['emp_no' => 'ECW 301', 'branch_id' => 1, 'basic_salary' => 0, 'status' => 1]);
        $this->artisan('hrmis:import:pay-stage', ['--company' => 'CO1']);
        $this->artisan('hrmis:import:pay-validate', ['--company' => 'CO1']);
        $this->assertTrue(StgIssue::query()->where('code', 'PAY003')->exists());
    }

    public function test_pay004_two_profiles_same_employee_number(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 401']);
        $this->legacyProfile(['emp_no' => 'ECW 401', 'branch_id' => 1, 'status' => 1]);
        $this->legacyProfile(['emp_no' => 'ECW 401', 'branch_id' => 1, 'status' => 1]);
        $this->artisan('hrmis:import:pay-stage', ['--company' => 'CO1']);
        $this->artisan('hrmis:import:pay-validate', ['--company' => 'CO1']);
        $this->assertTrue(StgIssue::query()->where('code', 'PAY004')->exists());
    }

    public function test_pay005_adjustment_type_not_in_mapping(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 501']);
        $profileId = $this->legacyProfile(['emp_no' => 'ECW 501', 'branch_id' => 1, 'status' => 1]);
        $typeId = $this->legacyAdjustmentType(['id' => 99, 'name' => 'Unknown bonus']);
        $this->legacyEmployeeAdjustment($profileId, $typeId, 1000);
        $this->artisan('hrmis:import:pay-stage', ['--company' => 'CO1']);
        $this->artisan('hrmis:import:pay-validate', ['--company' => 'CO1']);
        $this->assertTrue(StgIssue::query()->where('code', 'PAY005')->exists());
    }

    public function test_pay006_entry_or_ignore_not_loaded_count(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 601']);
        $profileId = $this->legacyProfile(['emp_no' => 'ECW 601', 'branch_id' => 1, 'status' => 1]);
        $this->legacyAdjustmentType(['id' => 5, 'name' => 'Meals']);
        $this->legacyEmployeeAdjustment($profileId, 5, 500);
        $this->artisan('hrmis:import:pay-stage', ['--company' => 'CO1']);
        $this->artisan('hrmis:import:pay-validate', ['--company' => 'CO1']);
        $this->assertTrue(StgIssue::query()->where('code', 'PAY006')->exists());
    }

    public function test_pay007_fixed_ot_without_rate(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 701']);
        $this->legacyProfile([
            'emp_no' => 'ECW 701',
            'branch_id' => 1,
            'ot_rate_mode' => 'fixed',
            'basic_salary' => 60000,
            'status' => 1,
        ]);
        $this->artisan('hrmis:import:pay-stage', ['--company' => 'CO1']);
        $this->artisan('hrmis:import:pay-validate', ['--company' => 'CO1']);
        $this->assertTrue(StgIssue::query()->where('code', 'PAY007')->exists());
    }

    public function test_pay008_unknown_attendance_type(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 801']);
        $this->legacyProfile([
            'emp_no' => 'ECW 801',
            'branch_id' => 1,
            'attendance_type' => 'type9',
            'basic_salary' => 60000,
            'status' => 1,
        ]);
        $this->artisan('hrmis:import:pay-stage', ['--company' => 'CO1']);
        $this->artisan('hrmis:import:pay-validate', ['--company' => 'CO1']);
        $this->assertTrue(StgIssue::query()->where('code', 'PAY008')->exists());
    }

    public function test_pay009_loan_approval_fields_disagree(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 901']);
        $profileId = $this->legacyProfile(['emp_no' => 'ECW 901', 'branch_id' => 1, 'basic_salary' => 60000, 'status' => 1]);
        $this->legacyLoan($profileId, [
            'if_approved' => 1,
            'status' => 0,
            'approved_amount' => 60000,
            'paid' => 1,
        ], [
            ['month' => '2027-01-01', 'amount' => 10000, 'paid' => 0],
        ]);
        $this->artisan('hrmis:import:pay-stage', ['--company' => 'CO1']);
        $this->artisan('hrmis:import:pay-validate', ['--company' => 'CO1']);
        $this->assertTrue(StgIssue::query()->where('code', 'PAY009')->exists());
    }

    public function test_pay010_unpaid_instalment_before_opening_month(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 910']);
        $profileId = $this->legacyProfile(['emp_no' => 'ECW 910', 'branch_id' => 1, 'basic_salary' => 60000, 'status' => 1]);
        $this->legacyLoan($profileId, [
            'if_approved' => 1,
            'status' => 1,
            'approved_amount' => 30000,
            'paid' => 1,
        ], [
            ['month' => '2025-01-01', 'amount' => 10000, 'paid' => 0],
        ]);
        $this->artisan('hrmis:import:pay-stage', ['--company' => 'CO1']);
        $this->artisan('hrmis:import:pay-validate', ['--company' => 'CO1'])->assertSuccessful();
        $this->assertTrue(StgIssue::query()->where('code', 'PAY010')->exists());
    }

    public function test_pay011_hostel_without_rate(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 911']);
        $this->legacyAdjustmentType(['id' => 4, 'name' => 'Hostel']);
        $this->legacyProfile([
            'emp_no' => 'ECW 911',
            'branch_id' => 1,
            'hostal' => 1,
            'basic_salary' => 60000,
            'status' => 1,
        ]);
        $this->artisan('hrmis:import:pay-stage', ['--company' => 'CO1']);
        $this->artisan('hrmis:import:pay-validate', ['--company' => 'CO1']);
        $this->assertTrue(StgIssue::query()->where('code', 'PAY011')->exists());
    }

    public function test_pay012_negative_or_non_numeric_amount(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $e->update(['legacy_employee_no' => 'ECW 912']);
        $profileId = $this->legacyProfile(['emp_no' => 'ECW 912', 'branch_id' => 1, 'basic_salary' => -100, 'status' => 1]);
        $this->legacyAdjustmentType(['id' => 1, 'name' => 'Fuel']);
        $this->legacyEmployeeAdjustment($profileId, 1, -50);
        $this->artisan('hrmis:import:pay-stage', ['--company' => 'CO1']);
        $this->artisan('hrmis:import:pay-validate', ['--company' => 'CO1']);
        $this->assertTrue(StgIssue::query()->where('code', 'PAY012')->exists());
    }
}
