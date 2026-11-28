<?php

namespace Modules\LegacyImport\Tests\Feature\Pay;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Calendar\Services\PayPeriodService;
use Modules\LegacyImport\Tests\Support\LegacyFixtures;
use Modules\Organisation\Models\Company;
use Modules\Payroll\Database\Seeders\PayComponentSeeder;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Enums\PayRunEmployeeStatus;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunEmployee;
use Modules\Payroll\Models\PayRunLine;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class PayCompareTest extends TestCase
{
    use HrFixtures;
    use LegacyFixtures;
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PayComponentSeeder::class);
        $this->setUpLegacyFixtures();
        $this->company = Company::query()->where('code', 'CO1')->firstOrFail();
    }

    public function test_matching_employee_is_marked_yes_within_one_rupee(): void
    {
        $this->seedV2AndLegacy('ECW 001', v2Net: '57217.00', legacyNet: '57217.50');

        $this->artisan('hrmis:compare:legacy-pay', [
            'period' => '2026-12',
            '--company' => 'CO1',
        ])->expectsOutputToContain('Matched: 1')->assertSuccessful();
    }

    public function test_differences_are_listed_per_figure(): void
    {
        $this->seedV2AndLegacy('ECW 001', v2Net: '50000.00', legacyNet: '40000.00', v2Gross: '60000.00', legacyGross: '55000.00');

        $this->artisan('hrmis:compare:legacy-pay', [
            'period' => '2026-12',
            '--company' => 'CO1',
        ])->expectsOutputToContain('Different: 1')->assertSuccessful();

        $this->assertFileExists(storage_path('app/private/compare/pay-compare-CO1-2026-12.xlsx'));
    }

    public function test_employees_only_on_one_side_are_listed_separately(): void
    {
        $this->seedV2Only('ECW 010');
        $this->legacyPayCycle(2026, 12, [
            ['emp_no' => 'ECW 020', 'net_pay' => 1000, 'basic_salary' => 1000, 'gross' => 1000, 'epf_employee' => 0, 'total_deductions' => 0],
        ]);

        $this->artisan('hrmis:compare:legacy-pay', [
            'period' => '2026-12',
            '--company' => 'CO1',
        ])
            ->expectsOutputToContain('Only in v2: 1')
            ->expectsOutputToContain('Only in legacy: 1')
            ->assertSuccessful();
    }

    public function test_stops_when_the_legacy_cycle_is_missing(): void
    {
        $this->artisan('hrmis:compare:legacy-pay', [
            'period' => '2026-12',
            '--company' => 'CO1',
        ])->expectsOutputToContain('No legacy pay cycle for 2026-12')->assertFailed();
    }

    public function test_comparison_writes_to_neither_database(): void
    {
        $this->seedV2AndLegacy('ECW 001', v2Net: '100.00', legacyNet: '100.00');
        DB::connection('legacy_payroll')->enableQueryLog();
        $before = PayRunEmployee::query()->count();

        $this->artisan('hrmis:compare:legacy-pay', [
            'period' => '2026-12',
            '--company' => 'CO1',
        ])->assertSuccessful();

        $this->assertSame($before, PayRunEmployee::query()->count());
        foreach (DB::connection('legacy_payroll')->getQueryLog() as $q) {
            $this->assertStringStartsWith('select', strtolower(ltrim($q['query'])));
        }
    }

    private function seedV2AndLegacy(
        string $empNo,
        string $v2Net,
        string $legacyNet,
        string $v2Gross = '60000.00',
        string $legacyGross = '60000.00',
    ): void {
        $employment = $this->hired('2026-01-01', $this->company);
        $assignment = $employment->currentAssignment();
        $period = app(PayPeriodService::class)->ensure($this->company, 2026, 12);
        $run = PayRun::query()->create([
            'company_id' => $this->company->id,
            'pay_period_id' => $period->id,
            'status' => PayRunStatus::CALCULATED,
        ]);
        $row = PayRunEmployee::query()->create([
            'pay_run_id' => $run->id,
            'company_id' => $this->company->id,
            'employment_id' => $employment->id,
            'status' => PayRunEmployeeStatus::OK,
            'employee_no' => $empNo,
            'name' => 'Test',
            'employee_category_id' => $this->categoryFor($employment)->id,
            'org_unit_id' => $assignment->org_unit_id,
            'designation_id' => $assignment->designation_id,
            'employment_type' => $employment->type,
            'epf_liable' => true,
            'gross_sheet1' => $v2Gross,
            'gross_sheet2' => '0.00',
            'total_deductions' => '0.00',
            'net_pay' => $v2Net,
            'employer_total' => '0.00',
            'epf_base' => '0.00',
            'taxable_pay' => '0.00',
            'carried_forward_in' => '0.00',
            'carried_forward_out' => '0.00',
            'basic_salary' => '60000.00',
            'ot_rate' => '0.00',
            'expected_days' => 25,
            'credited_days' => 25,
            'extra_days' => 0,
            'nopay_days' => 0,
            'late_minutes_chargeable' => 0,
            'ot_minutes' => 0,
            'is_cash' => false,
        ]);

        $basic = PayComponent::query()->where('code', 'BASIC')->first();
        $epf = PayComponent::query()->where('code', 'EPF_EE')->first();
        if ($basic) {
            PayRunLine::query()->create([
                'pay_run_employee_id' => $row->id,
                'pay_component_id' => $basic->id,
                'code' => 'BASIC',
                'kind' => ComponentKind::EARNING,
                'sheet' => 1,
                'amount' => '60000.00',
                'is_override' => false,
                'display_order' => 1,
            ]);
        }
        if ($epf) {
            PayRunLine::query()->create([
                'pay_run_employee_id' => $row->id,
                'pay_component_id' => $epf->id,
                'code' => 'EPF_EE',
                'kind' => ComponentKind::DEDUCTION,
                'sheet' => 1,
                'amount' => '4800.00',
                'is_override' => false,
                'display_order' => 2,
            ]);
        }

        $this->legacyPayCycle(2026, 12, [[
            'emp_no' => $empNo,
            'basic_salary' => 60000,
            'gross' => $legacyGross,
            'epf_employee' => 4800,
            'total_deductions' => 0,
            'net_pay' => $legacyNet,
        ]]);
    }

    private function seedV2Only(string $empNo): void
    {
        $employment = $this->hired('2026-01-01', $this->company);
        $assignment = $employment->currentAssignment();
        $period = app(PayPeriodService::class)->ensure($this->company, 2026, 12);
        $run = PayRun::query()->create([
            'company_id' => $this->company->id,
            'pay_period_id' => $period->id,
            'status' => PayRunStatus::CALCULATED,
        ]);
        PayRunEmployee::query()->create([
            'pay_run_id' => $run->id,
            'company_id' => $this->company->id,
            'employment_id' => $employment->id,
            'status' => PayRunEmployeeStatus::OK,
            'employee_no' => $empNo,
            'name' => 'Only V2',
            'employee_category_id' => $this->categoryFor($employment)->id,
            'org_unit_id' => $assignment->org_unit_id,
            'designation_id' => $assignment->designation_id,
            'employment_type' => $employment->type,
            'epf_liable' => false,
            'gross_sheet1' => '1000.00',
            'gross_sheet2' => '0.00',
            'total_deductions' => '0.00',
            'net_pay' => '1000.00',
            'employer_total' => '0.00',
            'epf_base' => '0.00',
            'taxable_pay' => '0.00',
            'carried_forward_in' => '0.00',
            'carried_forward_out' => '0.00',
            'basic_salary' => '1000.00',
            'ot_rate' => '0.00',
            'expected_days' => 25,
            'credited_days' => 25,
            'extra_days' => 0,
            'nopay_days' => 0,
            'late_minutes_chargeable' => 0,
            'ot_minutes' => 0,
            'is_cash' => false,
        ]);
    }
}
