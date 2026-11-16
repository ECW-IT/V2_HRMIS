<?php

namespace Modules\Payroll\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Services\PayPeriodService;
use Modules\Organisation\Models\Company;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Exceptions\InvalidLedgerEntry;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Services\ArrearsService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class ArrearsServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private ArrearsService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->svc = app(ArrearsService::class);
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

    private function runId($company, int $month = 12): int
    {
        $period = app(PayPeriodService::class)->ensure($company, 2026, $month);

        return PayRun::query()->firstOrCreate(
            ['pay_period_id' => $period->id],
            ['company_id' => $company->id, 'status' => PayRunStatus::DRAFT],
        )->id;
    }

    public function test_balance_is_the_sum_of_entries(): void
    {
        $e = $this->hired();
        $periodId = $this->periodId($e->company);
        $run1 = $this->runId($e->company, 11);
        $run2 = $this->runId($e->company, 12);

        $this->svc->recordShortfall($e->id, $run1, $periodId, '4600.00');
        $this->svc->recordRecovery($e->id, $run2, $periodId, '4600.00');
        $this->assertSame('0.00', $this->svc->balance($e->id));
        $this->assertCount(2, $this->svc->entries($e->id));

        $e2 = $this->hired(c: $e->company);
        $this->svc->recordShortfall($e2->id, $run1, $periodId, '4600.00');
        $this->svc->recordRecovery($e2->id, $run2, $periodId, '1000.00');
        $this->assertSame('3600.00', $this->svc->balance($e2->id));
    }

    public function test_recovery_cannot_exceed_the_balance(): void
    {
        $e = $this->hired();
        $periodId = $this->periodId($e->company);
        $run1 = $this->runId($e->company, 11);
        $run2 = $this->runId($e->company, 12);
        $this->svc->recordShortfall($e->id, $run1, $periodId, '1000.00');

        $this->expectException(InvalidLedgerEntry::class);
        $this->svc->recordRecovery($e->id, $run2, $periodId, '1000.01');
    }

    public function test_one_shortfall_and_one_recovery_per_run(): void
    {
        $e = $this->hired();
        $periodId = $this->periodId($e->company);
        $run1 = $this->runId($e->company, 12);
        $this->svc->recordShortfall($e->id, $run1, $periodId, '1000.00');

        try {
            $this->svc->recordShortfall($e->id, $run1, $periodId, '500.00');
            $this->fail();
        } catch (InvalidLedgerEntry) {
            // expected
        }

        $this->svc->recordRecovery($e->id, $run1, $periodId, '500.00');

        $this->expectException(InvalidLedgerEntry::class);
        $this->svc->recordRecovery($e->id, $run1, $periodId, '100.00');
    }

    public function test_entries_cannot_be_changed_or_deleted(): void
    {
        $e = $this->hired();
        $periodId = $this->periodId($e->company);
        $entry = $this->svc->recordShortfall($e->id, $this->runId($e->company), $periodId, '1000.00');

        try {
            $entry->amount = '1.00';
            $entry->save();
            $this->fail();
        } catch (\LogicException) {
            // expected
        }

        $this->expectException(\LogicException::class);
        $entry->delete();
    }

    public function test_opening_only_for_an_empty_ledger(): void
    {
        $e = $this->hired();
        $by = $this->adjuster($e->company);

        $this->svc->opening($e, '2500.00', 'Opening balance from legacy', $by);
        $this->assertSame('2500.00', $this->svc->balance($e->id));

        $this->expectException(InvalidLedgerEntry::class);
        $this->svc->opening($e, '100.00', 'Second opening not allowed', $by);
    }

    public function test_write_off_needs_permission_reason_and_a_balance(): void
    {
        $e = $this->hired();
        $periodId = $this->periodId($e->company);
        $this->svc->recordShortfall($e->id, $this->runId($e->company), $periodId, '1000.00');

        $noPerm = User::factory()
            ->forCompanies([$e->company])
            ->withRole('hr-manager')
            ->create(['must_change_password' => false]);

        try {
            $this->svc->writeOff($e, '500.00', 'Write off debt', $noPerm);
            $this->fail();
        } catch (AuthorizationException) {
            // expected
        }

        $by = $this->adjuster($e->company);

        try {
            $this->svc->writeOff($e, '500.00', 'no', $by);
            $this->fail();
        } catch (InvalidLedgerEntry) {
            // short reason
        }

        $this->svc->writeOff($e, '400.00', 'Write off part', $by);
        $this->assertSame('600.00', $this->svc->balance($e->id));

        $this->expectException(InvalidLedgerEntry::class);
        $this->svc->writeOff($e, '700.00', 'Too much write off', $by);
    }

    public function test_balances_lists_only_non_zero_and_only_the_company(): void
    {
        $a = $this->hired();
        $b = $this->hired(c: $a->company);
        $other = $this->hired(c: Company::factory()->create());
        $periodA = $this->periodId($a->company);
        $periodOther = $this->periodId($other->company);

        $runA1 = $this->runId($a->company, 11);
        $runA2 = $this->runId($a->company, 12);
        $runOther = $this->runId($other->company, 12);
        $this->svc->recordShortfall($a->id, $runA1, $periodA, '1000.00');
        $this->svc->recordShortfall($b->id, $runA1, $periodA, '500.00');
        $this->svc->recordRecovery($b->id, $runA2, $periodA, '500.00');
        $this->svc->recordShortfall($other->id, $runOther, $periodOther, '900.00');

        $balances = $this->svc->balances((int) $a->company_id);

        $this->assertSame([$a->id => '1000.00'], $balances);
        $this->assertArrayNotHasKey($b->id, $balances);
        $this->assertArrayNotHasKey($other->id, $balances);
    }
}
