<?php

namespace Modules\Payroll\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Grade;
use Modules\Payroll\Database\Seeders\PayComponentSeeder;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Enums\PayRunEmployeeStatus;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Exceptions\InvalidYtdOpening;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunEmployee;
use Modules\Payroll\Models\PayRunLine;
use Modules\Payroll\Services\YtdService;
use Modules\Payroll\Support\TaxYear;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class YtdServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private YtdService $svc;

    private Company $company;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PayComponentSeeder::class);
        $this->travelTo('2026-12-15');

        $this->company = $this->company();
        $this->officer = User::factory()
            ->forCompanies([$this->company])
            ->withRole('payroll-officer')
            ->create(['must_change_password' => false]);
        $this->svc = app(YtdService::class);
    }

    /** Tax example of Task 32: basic 250k + FUEL 30k + PIECE_RATE 50k. */
    private function taxMonthFigures(): array
    {
        return [
            'gross_sheet1' => '280000.00',
            'gross_sheet2' => '50000.00',
            'taxable_pay' => '330000.00',
            'APIT' => '26500.00',
            'EPF_EE' => '20000.00',
            'EPF_ER' => '30000.00',
            'ETF_ER' => '7500.00',
        ];
    }

    private function period(int $year, int $month): PayPeriod
    {
        return app(PayPeriodService::class)->ensure($this->company, $year, $month);
    }

    /**
     * Creates a LOCKED pay run for $employment in $period with the given sheet totals and line amounts.
     *
     * @param  array{gross_sheet1: string, gross_sheet2: string, taxable_pay: string, APIT: string, EPF_EE: string, EPF_ER: string, ETF_ER: string}  $figures
     */
    private function lockedRun(Employment $employment, PayPeriod $period, array $figures): PayRun
    {
        $run = PayRun::query()->create([
            'company_id' => $period->company_id,
            'pay_period_id' => $period->id,
            'status' => PayRunStatus::APPROVED,
            'employee_count' => 1,
        ]);

        $row = PayRunEmployee::query()->create([
            'pay_run_id' => $run->id,
            'company_id' => $employment->company_id,
            'employment_id' => $employment->id,
            'status' => PayRunEmployeeStatus::OK,
            'employee_no' => $employment->employee_no,
            'name' => 'YTD Employee',
            'grade_id' => $employment->assignmentOn($period->end_date)?->grade_id
                ?? Grade::factory()->create()->id,
            'employment_type' => 'PERMANENT',
            'epf_liable' => true,
            'basic_salary' => '250000.00',
            'ot_rate' => '0.00',
            'extra_day_method' => 'HOURLY',
            'expected_days' => '25.0',
            'credited_days' => '25.00',
            'extra_days' => '0.00',
            'nopay_days' => '0.00',
            'late_minutes_chargeable' => 0,
            'ot_minutes' => 0,
            'carried_forward_in' => '0.00',
            'is_cash' => true,
            'gross_sheet1' => $figures['gross_sheet1'],
            'gross_sheet2' => $figures['gross_sheet2'],
            'total_deductions' => '0.00',
            'net_pay' => '0.00',
            'carried_forward_out' => '0.00',
            'epf_base' => '250000.00',
            'taxable_pay' => $figures['taxable_pay'],
            'employer_total' => '0.00',
        ]);

        $order = 10;
        foreach (['APIT' => ComponentKind::DEDUCTION, 'EPF_EE' => ComponentKind::DEDUCTION, 'EPF_ER' => ComponentKind::EMPLOYER, 'ETF_ER' => ComponentKind::EMPLOYER] as $code => $kind) {
            $component = PayComponent::query()->where('code', $code)->firstOrFail();
            PayRunLine::query()->create([
                'pay_run_employee_id' => $row->id,
                'pay_component_id' => $component->id,
                'code' => $code,
                'kind' => $kind,
                'sheet' => 1,
                'amount' => $figures[$code],
                'is_override' => false,
                'display_order' => $order,
            ]);
            $order += 10;
        }

        $run->status = PayRunStatus::LOCKED;
        $run->locked_by = $this->officer->id;
        $run->locked_at = now();
        $run->save();

        return $run->fresh();
    }

    private function openingFigures(array $overrides = []): array
    {
        return array_merge([
            'months_covered' => 8,
            'gross' => '0.00',
            'taxable_pay' => '0.00',
            'tax' => '100000.00',
            'epf_employee' => '0.00',
            'epf_employer' => '0.00',
            'etf' => '0.00',
            'note' => 'Opening for mid-year go-live',
        ], $overrides);
    }

    public function test_sums_locked_runs_in_the_tax_year(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $figures = $this->taxMonthFigures();

        $this->lockedRun($e, $this->period(2026, 12), $figures);
        $this->lockedRun($e, $this->period(2027, 1), $figures);

        $ytd = $this->svc->for($e->id, '2026-04-01');

        $this->assertSame('660000.00', $ytd->gross);
        $this->assertSame('660000.00', $ytd->taxablePay);
        $this->assertSame('53000.00', $ytd->tax);
        $this->assertSame('40000.00', $ytd->epfEmployee);
        $this->assertSame('60000.00', $ytd->epfEmployer);
        $this->assertSame('15000.00', $ytd->etf);
        $this->assertSame(2, $ytd->months);
    }

    public function test_runs_that_are_not_locked_do_not_count(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $period = $this->period(2026, 12);
        $figures = $this->taxMonthFigures();

        $run = PayRun::query()->create([
            'company_id' => $period->company_id,
            'pay_period_id' => $period->id,
            'status' => PayRunStatus::APPROVED,
            'employee_count' => 1,
        ]);
        PayRunEmployee::query()->create([
            'pay_run_id' => $run->id,
            'company_id' => $e->company_id,
            'employment_id' => $e->id,
            'status' => PayRunEmployeeStatus::OK,
            'employee_no' => $e->employee_no,
            'name' => 'YTD Employee',
            'grade_id' => Grade::factory()->create()->id,
            'employment_type' => 'PERMANENT',
            'epf_liable' => true,
            'basic_salary' => '250000.00',
            'ot_rate' => '0.00',
            'extra_day_method' => 'HOURLY',
            'expected_days' => '25.0',
            'credited_days' => '25.00',
            'extra_days' => '0.00',
            'nopay_days' => '0.00',
            'late_minutes_chargeable' => 0,
            'ot_minutes' => 0,
            'carried_forward_in' => '0.00',
            'is_cash' => true,
            'gross_sheet1' => $figures['gross_sheet1'],
            'gross_sheet2' => $figures['gross_sheet2'],
            'total_deductions' => '0.00',
            'net_pay' => '0.00',
            'carried_forward_out' => '0.00',
            'epf_base' => '250000.00',
            'taxable_pay' => $figures['taxable_pay'],
            'employer_total' => '0.00',
        ]);

        $ytd = $this->svc->for($e->id, '2026-04-01');

        $this->assertSame('0.00', $ytd->gross);
        $this->assertSame('0.00', $ytd->tax);
        $this->assertSame(0, $ytd->months);
    }

    public function test_march_and_april_fall_in_different_tax_years(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $figures = $this->taxMonthFigures();

        $this->lockedRun($e, $this->period(2027, 3), $figures);
        $this->lockedRun($e, $this->period(2027, 4), $figures);

        $this->assertSame('2026-04-01', TaxYear::startFor('2027-03-31'));
        $this->assertSame('2027-04-01', TaxYear::startFor('2027-04-01'));

        $before = $this->svc->for($e->id, '2026-04-01');
        $this->assertSame('330000.00', $before->gross);
        $this->assertSame('26500.00', $before->tax);
        $this->assertSame(1, $before->months);

        $after = $this->svc->for($e->id, '2027-04-01');
        $this->assertSame('330000.00', $after->gross);
        $this->assertSame('26500.00', $after->tax);
        $this->assertSame(1, $after->months);
    }

    public function test_up_to_limits_the_periods_counted(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $figures = $this->taxMonthFigures();
        $dec = $this->period(2026, 12);
        $jan = $this->period(2027, 1);

        $this->lockedRun($e, $dec, $figures);
        $this->lockedRun($e, $jan, $figures);

        $upToDec = $this->svc->for($e->id, '2026-04-01', $dec);
        $this->assertSame('330000.00', $upToDec->gross);
        $this->assertSame('26500.00', $upToDec->tax);
        $this->assertSame(1, $upToDec->months);

        $upToJan = $this->svc->for($e->id, '2026-04-01', $jan);
        $this->assertSame('660000.00', $upToJan->gross);
        $this->assertSame(2, $upToJan->months);
    }

    public function test_opening_row_is_added_to_the_runs(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $this->actingAs($this->officer);
        Gate::authorize('payroll.config');

        $this->svc->setOpening($e, '2026-04-01', $this->openingFigures(), $this->officer);
        $this->lockedRun($e, $this->period(2026, 12), $this->taxMonthFigures());

        $ytd = $this->svc->for($e->id, '2026-04-01');

        $this->assertSame('126500.00', $ytd->tax);
        $this->assertSame(9, $ytd->months);
        $this->assertSame('330000.00', $ytd->gross);
        $this->assertSame('330000.00', $ytd->taxablePay);
        $this->assertSame('20000.00', $ytd->epfEmployee);
        $this->assertSame('30000.00', $ytd->epfEmployer);
        $this->assertSame('7500.00', $ytd->etf);
    }

    public function test_opening_cannot_be_set_after_a_locked_run_in_that_year(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $this->lockedRun($e, $this->period(2026, 12), $this->taxMonthFigures());

        $this->actingAs($this->officer);
        Gate::authorize('payroll.config');

        $this->expectException(InvalidYtdOpening::class);
        $this->svc->setOpening($e, '2026-04-01', $this->openingFigures(), $this->officer);
    }

    public function test_opening_needs_a_first_of_april_and_payroll_config(): void
    {
        $e = $this->hired('2026-01-01', $this->company);
        $this->actingAs($this->officer);
        Gate::authorize('payroll.config');

        try {
            $this->svc->setOpening($e, '2026-04-15', $this->openingFigures(), $this->officer);
            $this->fail('Expected InvalidYtdOpening for a non-1-April tax year start.');
        } catch (InvalidYtdOpening) {
            // expected
        }

        $outsider = User::factory()
            ->forCompanies([$this->company])
            ->create(['must_change_password' => false]);
        $this->actingAs($outsider);

        $this->expectException(AuthorizationException::class);
        $this->svc->setOpening($e, '2026-04-01', $this->openingFigures(), $outsider);
    }

    public function test_for_company_lists_everyone_with_figures(): void
    {
        $a = $this->hired('2026-01-01', $this->company);
        $b = $this->hired('2026-01-01', $this->company);
        $c = $this->hired('2026-01-01', $this->company);
        $figures = $this->taxMonthFigures();

        $this->lockedRun($a, $this->period(2026, 12), $figures);
        $this->lockedRun($b, $this->period(2027, 1), $figures);

        $this->actingAs($this->officer);
        Gate::authorize('payroll.config');
        $this->svc->setOpening($c, '2026-04-01', $this->openingFigures([
            'tax' => '5000.00',
            'gross' => '10000.00',
            'taxable_pay' => '10000.00',
        ]), $this->officer);

        $idle = $this->hired('2026-01-01', $this->company);

        $list = $this->svc->forCompany($this->company->id, '2026-04-01');

        $this->assertArrayHasKey($a->id, $list);
        $this->assertArrayHasKey($b->id, $list);
        $this->assertArrayHasKey($c->id, $list);
        $this->assertArrayNotHasKey($idle->id, $list);
        $this->assertCount(3, $list);
        $this->assertSame('330000.00', $list[$a->id]->gross);
        $this->assertSame('5000.00', $list[$c->id]->tax);
        $this->assertSame(8, $list[$c->id]->months);
    }
}
