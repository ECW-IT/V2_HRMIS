<?php

namespace Modules\Payroll\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Enums\FundCode;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Exceptions\ReportNotAvailable;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Reports\PayslipData;
use Modules\Payroll\Services\FundService;
use Modules\Payroll\Services\LoanService;
use Modules\Payroll\Tests\Support\ReportFixtures;
use Tests\TestCase;

class PayslipTest extends TestCase
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

    public function test_payslip_data_for_the_standard_example(): void
    {
        $data = app(PayslipData::class)->for($this->row($this->employeeA));

        $earnings = collect($data['earnings'])->keyBy('code');
        $this->assertSame('57600.00', $earnings['BASIC']['amount']);
        $this->assertSame('24.00', $earnings['BASIC']['quantity']);
        $this->assertSame('3750.00', $earnings['OT']['amount']);
        $this->assertSame('10.00', $earnings['OT']['quantity']);
        $this->assertSame('375.00', $earnings['OT']['rate']);
        $this->assertSame('4000.00', $earnings['ATT_ALLOW']['amount']);
        $this->assertSame('9600.00', $earnings['PERF']['amount']);
        $this->assertSame('5000.00', $earnings['FUEL']['amount']);

        $deductions = collect($data['deductions'])->keyBy('code');
        $this->assertSame('125.00', $deductions['LATE']['amount']);
        $this->assertSame('4608.00', $deductions['EPF_EE']['amount']);
        $this->assertSame('10000.00', $deductions['ADVANCE']['amount']);
        $this->assertSame('5000.00', $deductions['LOAN']['amount']);
        $this->assertSame('3000.00', $deductions['MEALS']['amount']);

        $this->assertSame('79950.00', $data['gross']);
        $this->assertSame('57217.00', $data['net_pay']);
        $this->assertSame('6912.00', $data['employer_epf']);
        $this->assertSame('1728.00', $data['employer_etf']);
        $this->assertSame('******5678', $data['account_no_masked']);
    }

    public function test_payslip_shows_the_carried_forward_amount(): void
    {
        $data = app(PayslipData::class)->for($this->row($this->employeeC));
        $this->assertSame('4600.00', $data['carried_forward_out']);
    }

    public function test_payslip_shows_loan_and_fund_balances_after_the_run(): void
    {
        $this->actingAs($this->officer);
        app(FundService::class)->adjust(
            $this->employeeA,
            FundCode::SAVINGS,
            '1500.00',
            'Opening savings for payslip test',
            $this->officer,
        );

        $this->actingAsViewer();
        $data = app(PayslipData::class)->for($this->row($this->employeeA));

        $loan = Loan::query()->where('employment_id', $this->employeeA->id)->firstOrFail();
        $this->assertSame('5000.00', app(LoanService::class)->outstanding($loan));
        $this->assertTrue(collect($data['loans'])->contains(fn ($row) => $row['outstanding'] === '5000.00'));
        $this->assertTrue(collect($data['funds'])->contains(
            fn ($row) => $row['fund'] === 'SAVINGS' && $row['balance'] === '1500.00'
        ));
    }

    public function test_payslip_is_not_available_before_approval(): void
    {
        DB::table('pay_runs')->where('id', $this->run->id)->update([
            'status' => PayRunStatus::CALCULATED->value,
            'approved_by' => null,
            'approved_at' => null,
            'locked_by' => null,
            'locked_at' => null,
        ]);
        $this->run->refresh();

        $this->expectException(ReportNotAvailable::class);
        app(PayslipData::class)->for($this->row($this->employeeA));
    }

    public function test_pdf_is_produced_and_named(): void
    {
        ini_set('memory_limit', '512M');

        $row = $this->row($this->employeeA);
        $response = $this->actingAs($this->viewer)
            ->get(route('payroll.payslips.show', ['run' => $this->run, 'employee' => $row]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('payslip-'.$row->employee_no.'-2026-12.pdf', $disposition);
    }

    public function test_payslip_route_needs_payroll_export_and_the_company(): void
    {
        $row = $this->row($this->employeeA);

        $noExport = User::factory()
            ->forCompanies([$this->company])
            ->withRole('auditor')
            ->create(['must_change_password' => false]);
        $this->actingAs($noExport)
            ->get(route('payroll.payslips.show', ['run' => $this->run, 'employee' => $row]))
            ->assertForbidden();

        $otherCompany = $this->company();
        $outsider = User::factory()
            ->forCompanies([$otherCompany])
            ->withRole('payroll-officer')
            ->create(['must_change_password' => false]);
        $this->actingAs($outsider)
            ->get(route('payroll.payslips.show', ['run' => $this->run->id, 'employee' => $row->id]))
            ->assertNotFound();
    }
}
