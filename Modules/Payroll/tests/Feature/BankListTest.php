<?php

namespace Modules\Payroll\Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\CoreHr\Services\BankAccountService;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Exceptions\ReportNotAvailable;
use Modules\Payroll\Reports\BankListReport;
use Modules\Payroll\Services\PayRunService;
use Modules\Payroll\Tests\Support\ReportFixtures;
use Tests\TestCase;

class BankListTest extends TestCase
{
    use RefreshDatabase;
    use ReportFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReportContext();
    }

    public function test_bank_cash_and_zero_groups(): void
    {
        $this->employeeA = $this->makeEmployeeA();
        $this->employeeB = $this->makeEmployeeB();
        $this->employeeC = $this->makeEmployeeC();

        $cashEmp = $this->paid('2026-01-01', $this->company);
        $this->hourly($cashEmp);
        app(BankAccountService::class)->set($cashEmp, ['effective_from' => '2026-01-01', 'is_cash' => true]);

        $this->period = $this->closedPeriod($this->company);
        $this->summary($this->employeeA, $this->period, [
            'credited_days' => 24,
            'late_minutes_chargeable' => 30,
            'ot_normal_minutes' => 600,
        ]);
        $this->summary($this->employeeB, $this->period);
        $this->summary($this->employeeC, $this->period);
        $this->summary($cashEmp, $this->period);

        $runs = app(PayRunService::class);
        $run = $runs->open($this->period, $this->officer);
        $runs->calculate($run, $this->officer);
        $run = $runs->approve($run->fresh(), $this->approver);
        $this->run = $runs->lock($run->fresh(), $this->officer);

        $this->actingAsViewer();
        $list = app(BankListReport::class)->build($this->run);

        $bankIds = collect($list['bank'])->pluck('employment_id')->all();
        $cashIds = collect($list['cash'])->pluck('employment_id')->all();
        $zeroIds = collect($list['zero'])->pluck('employment_id')->all();

        $this->assertContains($this->employeeA->id, $bankIds);
        $this->assertContains($this->employeeB->id, $bankIds);
        $this->assertContains($this->employeeC->id, $zeroIds);
        $this->assertContains($cashEmp->id, $cashIds);

        $paid = bcadd($list['totals']['bank']['amount'], $list['totals']['cash']['amount'], 2);
        $this->assertSame(number_format((float) $this->run->net_pay, 2, '.', ''), $paid);
    }

    public function test_reference_and_account_details_come_from_the_snapshot(): void
    {
        $this->buildLockedRun();
        $row = $this->row($this->employeeA);
        $snapshotAccount = $row->account_no;

        app(BankAccountService::class)->set($this->employeeA, $this->account([
            'effective_from' => '2027-01-01',
            'account_no' => '1111222233',
            'account_name' => 'Changed Later',
        ]));

        $this->actingAsViewer();
        $list = app(BankListReport::class)->build($this->run);
        $bankRow = collect($list['bank'])->firstWhere('employment_id', $this->employeeA->id);

        $this->assertSame($snapshotAccount, $bankRow['account_no']);
        $this->assertSame('N Perera', $bankRow['account_name']);
        $this->assertSame('SAL 2026-12 '.$row->employee_no, $bankRow['reference']);
        $this->assertNotSame('1111222233', $bankRow['account_no']);
    }

    public function test_bank_list_needs_an_approved_run_and_payroll_export(): void
    {
        $this->buildLockedRun();
        DB::table('pay_runs')->where('id', $this->run->id)->update([
            'status' => PayRunStatus::CALCULATED->value,
            'approved_by' => null,
            'approved_at' => null,
            'locked_by' => null,
            'locked_at' => null,
        ]);
        $this->run->refresh();

        $this->actingAsViewer();
        try {
            app(BankListReport::class)->build($this->run);
            $this->fail('Expected ReportNotAvailable');
        } catch (ReportNotAvailable) {
            // expected
        }

        DB::table('pay_runs')->where('id', $this->run->id)->update([
            'status' => PayRunStatus::LOCKED->value,
        ]);
        $this->run->refresh();

        $noExport = User::factory()
            ->forCompanies([$this->company])
            ->withRole('auditor')
            ->create(['must_change_password' => false]);
        $this->actingAs($noExport);
        $this->expectException(AuthorizationException::class);
        app(BankListReport::class)->build($this->run->fresh());
    }
}
