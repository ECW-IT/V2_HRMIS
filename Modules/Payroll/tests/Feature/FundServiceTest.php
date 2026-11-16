<?php

namespace Modules\Payroll\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Services\PayPeriodService;
use Modules\Payroll\Enums\FundCode;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Exceptions\InvalidLedgerEntry;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Services\FundService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class FundServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private FundService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->svc = app(FundService::class);
    }

    private function adjuster($company): User
    {
        return User::factory()
            ->forCompanies([$company])
            ->withRole('payroll-officer')
            ->create(['must_change_password' => false]);
    }

    private function periodId($company): int
    {
        return app(PayPeriodService::class)->ensure($company, 2026, 12)->id;
    }

    private function runId($company, int $month): int
    {
        $period = app(PayPeriodService::class)->ensure($company, 2026, $month);

        return PayRun::query()->firstOrCreate(
            ['pay_period_id' => $period->id],
            ['company_id' => $company->id, 'status' => PayRunStatus::DRAFT],
        )->id;
    }

    public function test_contributions_build_a_balance_per_fund(): void
    {
        $e = $this->hired();
        $periodId = $this->periodId($e->company);
        $r1 = $this->runId($e->company, 10);
        $r2 = $this->runId($e->company, 11);
        $r3 = $this->runId($e->company, 12);

        $this->svc->recordContribution($e->id, FundCode::SAVINGS, $r1, $periodId, '2000.00');
        $this->svc->recordContribution($e->id, FundCode::SAVINGS, $r2, $periodId, '2000.00');
        $this->svc->recordContribution($e->id, FundCode::SAVINGS, $r3, $periodId, '2000.00');
        $this->svc->recordContribution($e->id, FundCode::DEPOSIT, $r1, $periodId, '1000.00');

        $this->assertSame('6000.00', $this->svc->balance($e->id, FundCode::SAVINGS));
        $this->assertSame('1000.00', $this->svc->balance($e->id, FundCode::DEPOSIT));
        $this->assertSame('0.00', $this->svc->balance($e->id, FundCode::WELFARE));
    }

    public function test_one_contribution_per_fund_per_run(): void
    {
        $e = $this->hired();
        $periodId = $this->periodId($e->company);
        $run = $this->runId($e->company, 12);
        $this->svc->recordContribution($e->id, FundCode::SAVINGS, $run, $periodId, '1000.00');

        $this->expectException(InvalidLedgerEntry::class);
        $this->svc->recordContribution($e->id, FundCode::SAVINGS, $run, $periodId, '500.00');
    }

    public function test_refund_reduces_the_balance_and_cannot_exceed_it(): void
    {
        $e = $this->hired();
        $by = $this->adjuster($e->company);
        $periodId = $this->periodId($e->company);
        $this->svc->recordContribution($e->id, FundCode::SAVINGS, $this->runId($e->company, 12), $periodId, '2000.00');

        $this->svc->refund($e, FundCode::SAVINGS, '500.00', 'Partial refund', $by);
        $this->assertSame('1500.00', $this->svc->balance($e->id, FundCode::SAVINGS));

        $this->expectException(InvalidLedgerEntry::class);
        $this->svc->refund($e, FundCode::SAVINGS, '1500.01', 'Too much refund', $by);
    }

    public function test_welfare_is_not_refundable(): void
    {
        $e = $this->hired();
        $by = $this->adjuster($e->company);
        $periodId = $this->periodId($e->company);
        $this->svc->recordContribution($e->id, FundCode::WELFARE, $this->runId($e->company, 12), $periodId, '500.00');

        $this->expectException(InvalidLedgerEntry::class);
        $this->expectExceptionMessage('Welfare contributions are not refundable.');
        $this->svc->refund($e, FundCode::WELFARE, '100.00', 'Try refund', $by);
    }

    public function test_statement_is_in_date_order_with_a_running_balance(): void
    {
        $e = $this->hired();
        $by = $this->adjuster($e->company);
        $periodId = $this->periodId($e->company);

        $this->svc->recordContribution($e->id, FundCode::SAVINGS, $this->runId($e->company, 11), $periodId, '2000.00');
        $this->svc->recordContribution($e->id, FundCode::SAVINGS, $this->runId($e->company, 12), $periodId, '1000.00');
        $this->svc->refund($e, FundCode::SAVINGS, '500.00', 'Partial refund', $by);

        $statement = $this->svc->statement($e->id, FundCode::SAVINGS);
        $this->assertSame(['2000.00', '3000.00', '2500.00'], $statement->pluck('balance_after')->all());
        $this->assertSame(['CONTRIBUTION', 'CONTRIBUTION', 'REFUND'], $statement->pluck('kind')->all());
    }

    public function test_adjust_and_refund_need_ledger_adjust(): void
    {
        $e = $this->hired();
        $periodId = $this->periodId($e->company);
        $this->svc->recordContribution($e->id, FundCode::SAVINGS, $this->runId($e->company, 12), $periodId, '1000.00');

        $noPerm = User::factory()
            ->forCompanies([$e->company])
            ->withRole('hr-manager')
            ->create(['must_change_password' => false]);

        try {
            $this->svc->adjust($e, FundCode::SAVINGS, '100.00', 'Manual bump', $noPerm);
            $this->fail();
        } catch (AuthorizationException) {
            // expected
        }

        $this->expectException(AuthorizationException::class);
        $this->svc->refund($e, FundCode::SAVINGS, '100.00', 'Manual refund', $noPerm);
    }
}
