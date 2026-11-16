<?php

namespace Modules\Payroll\Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Organisation\Models\OrgUnit;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Reports\PaysheetReport;
use Modules\Payroll\Reports\RegisterReport;
use Modules\Payroll\Tests\Support\ReportFixtures;
use Tests\TestCase;

class PaysheetReportTest extends TestCase
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

    public function test_sheet_1_has_only_sheet_1_components_with_a_non_zero_line(): void
    {
        $report = app(PaysheetReport::class)->build($this->run, 1);
        $codes = array_keys($report['columns']);

        foreach (['BASIC', 'OT', 'ATT_ALLOW', 'PERF', 'FUEL', 'LATE', 'EPF_EE', 'APIT', 'LOAN', 'MEALS'] as $code) {
            $this->assertContains($code, $codes, $code);
        }
        foreach (['ADVANCE', 'PIECE_RATE', 'INCENTIVE_1'] as $code) {
            $this->assertNotContains($code, $codes, $code);
        }
    }

    public function test_sheet_2_lists_only_employees_with_sheet_2_lines(): void
    {
        $report = app(PaysheetReport::class)->build($this->run, 2);
        $byEmp = collect($report['rows'])->keyBy('employment_id');

        $this->assertCount(3, $report['rows']);
        $this->assertSame('-10000.00', $byEmp[$this->employeeA->id]['net']);
        $this->assertSame('10000.00', $byEmp[$this->employeeA->id]['ADVANCE']);
        $this->assertSame('50000.00', $byEmp[$this->employeeB->id]['PIECE_RATE']);
        $this->assertSame('15000.00', $byEmp[$this->employeeC->id]['ADVANCE']);
        $this->assertSame('50000.00', $report['totals']['gross']);
    }

    public function test_register_reconciles_the_sheets_to_net_pay(): void
    {
        $register = app(RegisterReport::class)->build($this->run);
        $byEmp = collect($register['rows'])->keyBy('employment_id');

        foreach ($register['rows'] as $row) {
            $sum = bcadd(bcadd($row['net_sheet1'], $row['net_sheet2'], 2), $row['carried_forward_out'], 2);
            $this->assertSame($row['net_pay'], $sum, $row['employee_no']);
        }

        $a = $byEmp[$this->employeeA->id];
        $this->assertSame('67217.00', $a['net_sheet1']);
        $this->assertSame('-10000.00', $a['net_sheet2']);
        $this->assertSame('57217.00', $a['net_pay']);

        $c = $byEmp[$this->employeeC->id];
        $this->assertSame('10400.00', $c['net_sheet1']);
        $this->assertSame('-15000.00', $c['net_sheet2']);
        $this->assertSame('4600.00', $c['carried_forward_out']);
        $this->assertSame('0.00', $c['net_pay']);

        $this->assertSame($this->accessMoney($this->run->gross_sheet1), $register['totals']['gross_sheet1']);
        $this->assertSame($this->accessMoney($this->run->gross_sheet2), $register['totals']['gross_sheet2']);
        $this->assertSame($this->accessMoney($this->run->net_pay), $register['totals']['net_pay']);
        $this->assertSame($this->accessMoney($this->run->total_deductions), $register['totals']['total_deductions']);
    }

    public function test_draft_marker_until_approved_and_org_unit_filter(): void
    {
        DB::table('pay_runs')->where('id', $this->run->id)->update([
            'status' => PayRunStatus::CALCULATED->value,
        ]);
        $this->run->refresh();

        $draft = app(PaysheetReport::class)->build($this->run, 1);
        $this->assertStringContainsString('DRAFT — not approved', $draft['title']);

        DB::table('pay_runs')->where('id', $this->run->id)->update([
            'status' => PayRunStatus::APPROVED->value,
        ]);
        $this->run->refresh();
        $this->assertStringNotContainsString(
            'DRAFT — not approved',
            app(PaysheetReport::class)->build($this->run, 1)['title']
        );

        DB::table('pay_runs')->where('id', $this->run->id)->update([
            'status' => PayRunStatus::LOCKED->value,
        ]);
        $this->run->refresh();

        $unit = OrgUnit::query()->findOrFail($this->row($this->employeeA)->org_unit_id);
        $filtered = app(PaysheetReport::class)->build($this->run, 1, $unit->id);
        $ids = collect($filtered['rows'])->pluck('employment_id')->all();
        $this->assertContains($this->employeeA->id, $ids);
        $this->assertNotContains($this->employeeB->id, $ids);
        $this->assertNotContains($this->employeeC->id, $ids);
    }

    public function test_reports_are_limited_to_the_users_companies_and_need_payroll_view(): void
    {
        $outsider = User::factory()->create(['must_change_password' => false]);
        $this->actingAs($outsider);
        $this->expectException(AuthorizationException::class);
        app(PaysheetReport::class)->build($this->run, 1);
    }

    private function accessMoney(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
