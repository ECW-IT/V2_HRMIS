<?php

namespace Modules\Payroll\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Reports\CarriedForwardReport;
use Modules\Payroll\Reports\EpfReport;
use Modules\Payroll\Reports\EtfReport;
use Modules\Payroll\Reports\TaxScheduleReport;
use Modules\Payroll\Tests\Support\ReportFixtures;
use Tests\TestCase;

class StatutoryReportsTest extends TestCase
{
    use RefreshDatabase;
    use ReportFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReportContext();
        $this->buildLockedRun();
        $this->actingAsViewer();
    }

    public function test_epf_list_totals(): void
    {
        $report = app(EpfReport::class)->build($this->run);
        $byEmp = collect($report['rows'])->keyBy('employment_id');

        $this->assertSame('57600.00', $byEmp[$this->employeeA->id]['epf_base']);
        $this->assertSame('4608.00', $byEmp[$this->employeeA->id]['employee']);
        $this->assertSame('6912.00', $byEmp[$this->employeeA->id]['employer']);

        $this->assertSame('250000.00', $byEmp[$this->employeeB->id]['epf_base']);
        $this->assertSame('20000.00', $byEmp[$this->employeeB->id]['employee']);
        $this->assertSame('30000.00', $byEmp[$this->employeeB->id]['employer']);

        $this->assertSame('20000.00', $byEmp[$this->employeeC->id]['epf_base']);
        $this->assertSame('1600.00', $byEmp[$this->employeeC->id]['employee']);
        $this->assertSame('2400.00', $byEmp[$this->employeeC->id]['employer']);

        $this->assertSame('327600.00', $report['totals']['epf_base']);
        $this->assertSame('26208.00', $report['totals']['employee']);
        $this->assertSame('39312.00', $report['totals']['employer']);
    }

    public function test_etf_list_totals(): void
    {
        $report = app(EtfReport::class)->build($this->run);
        $this->assertSame('9828.00', $report['totals']['etf']);
    }

    public function test_tax_schedule_lists_only_employees_with_tax(): void
    {
        $report = app(TaxScheduleReport::class)->build($this->run);
        $this->assertCount(1, $report['rows']);
        $this->assertSame($this->employeeB->id, $report['rows'][0]['employment_id']);
        $this->assertSame('330000.00', $report['rows'][0]['taxable_pay']);
        $this->assertSame('26500.00', $report['rows'][0]['tax']);
    }

    public function test_employee_not_epf_liable_is_not_on_the_epf_list(): void
    {
        $notLiable = $this->hired('2026-01-01', $this->company, ['epf_liable' => false]);
        DB::table('pay_run_employees')->insert([
            'pay_run_id' => $this->run->id,
            'company_id' => $this->company->id,
            'employment_id' => $notLiable->id,
            'status' => 'OK',
            'employee_no' => $notLiable->employee_no,
            'name' => 'No EPF',
            'grade_id' => $this->row($this->employeeA)->grade_id,
            'employment_type' => 'PERMANENT',
            'epf_liable' => 0,
            'basic_salary' => '10000.00',
            'ot_rate' => '0.00',
            'extra_day_method' => 'HOURLY',
            'expected_days' => 25,
            'credited_days' => 25,
            'extra_days' => 0,
            'nopay_days' => 0,
            'late_minutes_chargeable' => 0,
            'ot_minutes' => 0,
            'carried_forward_in' => 0,
            'is_cash' => 1,
            'gross_sheet1' => 10000,
            'gross_sheet2' => 0,
            'total_deductions' => 0,
            'net_pay' => 10000,
            'carried_forward_out' => 0,
            'epf_base' => 0,
            'taxable_pay' => 10000,
            'employer_total' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = app(EpfReport::class)->build($this->run);
        $ids = collect($report['rows'])->pluck('employment_id')->all();
        $this->assertNotContains($notLiable->id, $ids);
        $this->assertContains($this->employeeA->id, $ids);
    }

    public function test_carried_forward_list(): void
    {
        $report = app(CarriedForwardReport::class)->build($this->run);
        $this->assertCount(1, $report['rows']);
        $row = $report['rows'][0];
        $this->assertSame($this->employeeC->id, $row['employment_id']);
        $this->assertSame('0.00', $row['brought_in']);
        $this->assertSame('0.00', $row['recovered']);
        $this->assertSame('4600.00', $row['new_shortfall']);
        $this->assertSame('4600.00', $row['balance_after']);
    }
}
