<?php

namespace Modules\Payroll\Tests\Feature;

use App\Models\User;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Database\Seeders\LoanTypeSeeder;
use Modules\Payroll\Enums\AdvanceStatus;
use Modules\Payroll\Enums\LoanStatus;
use Modules\Payroll\Exceptions\InvalidAdvance;
use Modules\Payroll\Models\SalaryAdvance;
use Modules\Payroll\Services\AdvanceService;
use Modules\Workflow\Enums\ApprovalStatus;
use Modules\Workflow\Services\ApprovalService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class AdvanceServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private AdvanceService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
        $this->seed(LoanTypeSeeder::class);
        $this->svc = app(AdvanceService::class);
        $this->travelTo('2026-12-15');
    }

    private function user(string $role, Employment $e, array $extra = []): User
    {
        return User::factory()
            ->forCompanies([$e->company_id])
            ->withRole($role)
            ->withPermissions('approval.act', ...$extra)
            ->create(['must_change_password' => false]);
    }

    private function december(Employment $e)
    {
        return app(PayPeriodService::class)->ensure($e->company, 2026, 12);
    }

    private function november(Employment $e)
    {
        return app(PayPeriodService::class)->ensure($e->company, 2026, 11);
    }

    private function approve(SalaryAdvance $a, Employment $e): void
    {
        $req = $a->approvalRequest;
        app(ApprovalService::class)->approve($req, $this->user('hr-manager', $e));
    }

    public function test_request_goes_for_approval_and_approval_sets_approved(): void
    {
        $e = $this->paid();
        $by = $this->user('hr-officer', $e);
        $p = $this->december($e);

        $a = $this->svc->request($e, $p, '10000.00', 'Urgent', $by);

        $this->assertSame(AdvanceStatus::PENDING, $a->status);
        $this->assertSame(ApprovalStatus::PENDING, $a->approvalRequest->status);

        $this->approve($a, $e);

        $this->assertSame(AdvanceStatus::APPROVED, $a->fresh()->status);
    }

    public function test_advances_over_half_of_basic_are_refused(): void
    {
        $e = $this->paid();
        $by = $this->user('payroll-officer', $e);
        $p = $this->december($e);

        $a1 = $this->svc->request($e, $p, '20000.00', null, $by);
        $this->approve($a1, $e);
        $a2 = $this->svc->request($e, $p, '10000.00', null, $by);
        $this->approve($a2, $e);

        $this->expectException(InvalidAdvance::class);
        $this->svc->request($e, $p, '0.01', null, $by);
    }

    public function test_rejected_and_cancelled_advances_do_not_count_towards_the_limit(): void
    {
        $e = $this->paid();
        $by = $this->user('payroll-officer', $e);
        $p = $this->december($e);

        $rejected = $this->svc->request($e, $p, '30000.00', null, $by);
        app(ApprovalService::class)->reject($rejected->approvalRequest, $this->user('hr-manager', $e), 'No');
        $this->assertSame(AdvanceStatus::REJECTED, $rejected->fresh()->status);

        $cancelled = $this->svc->request($e, $p, '30000.00', null, $by);
        $this->approve($cancelled, $e);
        $this->svc->cancel($cancelled->fresh(), $by);

        $ok = $this->svc->request($e, $p, '30000.00', null, $by);
        $this->assertSame(AdvanceStatus::PENDING, $ok->status);
    }

    public function test_mark_paid_needs_advance_pay_and_an_approved_advance(): void
    {
        $e = $this->paid();
        $p = $this->december($e);
        $manager = $this->user('hr-manager', $e);
        $a = $this->svc->request($e, $p, '5000.00', null, $manager);
        $this->approve($a, $e);

        try {
            $this->svc->markPaid($a->fresh(), '2026-12-10', 'CHQ1', $manager);
            $this->fail();
        } catch (AuthorizationException) {
            // hr-manager lacks advance.pay
        }

        $pending = $this->svc->request($e, $p, '1000.00', null, $manager);
        $payer = $this->user('payroll-officer', $e);
        try {
            $this->svc->markPaid($pending, '2026-12-10', null, $payer);
            $this->fail();
        } catch (InvalidAdvance) {
            // not approved
        }

        $paid = $this->svc->markPaid($a->fresh(), '2026-12-10', 'CHQ1', $payer);
        $this->assertSame(AdvanceStatus::PAID, $paid->status);
        $this->assertSame('2026-12-10', $paid->paid_on->toDateString());
    }

    public function test_mark_paid_requires_a_payment_date(): void
    {
        $e = $this->paid();
        $p = $this->december($e);
        $manager = $this->user('hr-manager', $e);
        $payer = $this->user('payroll-officer', $e);
        $a = $this->svc->request($e, $p, '5000.00', null, $manager);
        $this->approve($a, $e);

        try {
            $this->svc->markPaid($a->fresh(), '', null, $payer);
            $this->fail();
        } catch (InvalidAdvance $ex) {
            $this->assertSame('A payment date is required.', $ex->getMessage());
        }

        $this->assertSame(AdvanceStatus::APPROVED, $a->fresh()->status);
    }

    public function test_due_for_returns_paid_advances_of_this_and_earlier_periods(): void
    {
        $e = $this->paid();
        $by = $this->user('payroll-officer', $e);
        $nov = $this->november($e);
        $dec = $this->december($e);

        $novPaid = $this->svc->request($e, $nov, '5000.00', null, $by);
        $this->approve($novPaid, $e);
        $this->svc->markPaid($novPaid->fresh(), '2026-11-20', null, $by);

        $decPaid = $this->svc->request($e, $dec, '4000.00', null, $by);
        $this->approve($decPaid, $e);
        $this->svc->markPaid($decPaid->fresh(), '2026-12-10', null, $by);

        $decApproved = $this->svc->request($e, $dec, '3000.00', null, $by);
        $this->approve($decApproved, $e);

        $due = $this->svc->dueFor($e->id, $dec);
        $this->assertSame([$novPaid->id, $decPaid->id], $due->pluck('id')->all());
    }

    public function test_paid_advance_cannot_be_cancelled(): void
    {
        $e = $this->paid();
        $by = $this->user('payroll-officer', $e);
        $p = $this->december($e);
        $a = $this->svc->request($e, $p, '5000.00', null, $by);
        $this->approve($a, $e);
        $this->svc->markPaid($a->fresh(), '2026-12-10', null, $by);

        $this->expectException(InvalidAdvance::class);
        $this->svc->cancel($a->fresh(), $by);
    }

    public function test_convert_to_loan_creates_an_active_loan_with_a_schedule(): void
    {
        $e = $this->paid();
        $by = $this->user('payroll-officer', $e);
        $p = $this->december($e);
        $a = $this->svc->request($e, $p, '30000.00', null, $by);
        $this->approve($a, $e);
        $this->svc->markPaid($a->fresh(), '2026-12-10', null, $by);

        $loan = $this->svc->convertToLoan($a->fresh(), 3, '2027-01-01', $by);

        $this->assertSame(LoanStatus::ACTIVE, $loan->status);
        $this->assertCount(3, $loan->instalmentRows);
        $this->assertSame(['10000.00', '10000.00', '10000.00'], $loan->instalmentRows->pluck('amount')->all());
        $this->assertSame(['2027-01-01', '2027-02-01', '2027-03-01'], $loan->instalmentRows->pluck('due_month')->map->toDateString()->all());
        $this->assertSame(AdvanceStatus::CONVERTED, $a->fresh()->status);
        $this->assertSame($loan->id, $a->fresh()->converted_loan_id);
        $this->assertCount(0, $this->svc->dueFor($e->id, $p));
    }

    public function test_nobody_requests_their_own_advance(): void
    {
        $e = $this->paid();
        $p = $this->december($e);
        $self = User::factory()
            ->forCompanies([$e->company_id])
            ->withRole('payroll-officer')
            ->create(['must_change_password' => false, 'person_id' => $e->person_id]);

        $this->expectException(AuthorizationException::class);
        $this->svc->request($e, $p, '1000.00', null, $self);
    }

    public function test_approved_unpaid_count(): void
    {
        $e = $this->paid();
        $by = $this->user('payroll-officer', $e);
        $p = $this->december($e);

        $a = $this->svc->request($e, $p, '5000.00', null, $by);
        $this->assertSame(0, $this->svc->approvedUnpaidCount($p));
        $this->approve($a, $e);
        $this->assertSame(1, $this->svc->approvedUnpaidCount($p));
        $this->svc->markPaid($a->fresh(), '2026-12-10', null, $by);
        $this->assertSame(0, $this->svc->approvedUnpaidCount($p));
    }
}
