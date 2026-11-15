<?php

namespace Modules\Payroll\Tests\Feature;

use App\Models\User;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Database\Seeders\LoanTypeSeeder;
use Modules\Payroll\Enums\InstalmentStatus;
use Modules\Payroll\Enums\LoanStatus;
use Modules\Payroll\Exceptions\InvalidLoan;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\LoanInstalment;
use Modules\Payroll\Services\LoanService;
use Modules\Workflow\Services\ApprovalService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class LoanServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private LoanService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
        $this->seed(LoanTypeSeeder::class);
        $this->svc = app(LoanService::class);
        $this->travelTo('2026-11-20');
    }

    private function user(string $role, Employment $e): User
    {
        return User::factory()
            ->forCompanies([$e->company_id])
            ->withRole($role)
            ->withPermissions('approval.act')
            ->create(['must_change_password' => false]);
    }

    private function approve(Loan $loan, Employment $e): Loan
    {
        app(ApprovalService::class)->approve($loan->approvalRequest, $this->user('hr-manager', $e));

        return $loan->fresh(['instalmentRows']);
    }

    public function test_schedule_puts_the_remainder_on_the_last_instalment(): void
    {
        $s = $this->svc->schedule('100000.00', 3, '2026-12-01');
        $this->assertSame(['33333.33', '33333.33', '33333.34'], array_column($s, 'amount'));
        $this->assertSame(['2026-12-01', '2027-01-01', '2027-02-01'], array_column($s, 'due_month'));
        $this->assertSame(['12500.00', '12500.00', '12500.00', '12500.00'], array_column($this->svc->schedule('50000.00', 4, '2026-12-01'), 'amount'));
    }

    public function test_approval_creates_the_instalments_and_payment_activates_the_loan(): void
    {
        $e = $this->paid();
        $by = $this->user('payroll-officer', $e);
        $loan = $this->svc->request($e, [
            'loan_type_code' => 'STAFF',
            'amount' => '30000.00',
            'instalments' => 3,
            'first_month' => '2026-12-01',
        ], $by);

        $this->assertSame(LoanStatus::PENDING, $loan->status);
        $this->assertCount(0, $loan->instalmentRows);

        $loan = $this->approve($loan, $e);
        $this->assertSame(LoanStatus::APPROVED, $loan->status);
        $this->assertCount(3, $loan->instalmentRows);

        $active = $this->svc->markPaid($loan, '2026-11-20', 'REF', $by);
        $this->assertSame(LoanStatus::ACTIVE, $active->status);
    }

    public function test_only_active_loans_have_instalments_due(): void
    {
        $e = $this->paid();
        $by = $this->user('payroll-officer', $e);
        $loan = $this->svc->request($e, [
            'loan_type_code' => 'STAFF',
            'amount' => '30000.00',
            'instalments' => 3,
            'first_month' => '2026-12-01',
        ], $by);
        $this->approve($loan, $e);

        $dec = app(PayPeriodService::class)->ensure($e->company, 2026, 12);
        $this->assertCount(0, $this->svc->dueFor($e->id, $dec));
    }

    public function test_due_for_includes_unrecovered_earlier_months(): void
    {
        $e = $this->paid();
        $by = $this->user('payroll-officer', $e);
        $loan = $this->svc->request($e, [
            'loan_type_code' => 'STAFF',
            'amount' => '30000.00',
            'instalments' => 3,
            'first_month' => '2026-11-01',
        ], $by);
        $loan = $this->approve($loan, $e);
        $this->svc->markPaid($loan, '2026-11-20', null, $by);

        $this->travelTo('2027-01-15');
        $jan = app(PayPeriodService::class)->ensure($e->company, 2027, 1);
        $due = $this->svc->dueFor($e->id, $jan);

        $this->assertSame(['2026-11-01', '2026-12-01', '2027-01-01'], $due->pluck('due_month')->map->toDateString()->all());
    }

    public function test_grace_moves_the_instalment_to_the_end(): void
    {
        $e = $this->paid();
        $by = $this->user('payroll-officer', $e);
        $loan = $this->svc->request($e, [
            'loan_type_code' => 'STAFF',
            'amount' => '30000.00',
            'instalments' => 3,
            'first_month' => '2026-12-01',
        ], $by);
        $loan = $this->approve($loan, $e);
        $this->svc->markPaid($loan, '2026-11-20', null, $by);

        $loan = $this->svc->grace($loan->fresh(), '2027-01-01', 'Medical expenses', $by);
        $byMonth = $loan->instalmentRows->keyBy(fn ($i) => $i->due_month->toDateString());

        $this->assertSame(InstalmentStatus::DUE, $byMonth['2026-12-01']->status);
        $this->assertSame(InstalmentStatus::MOVED, $byMonth['2027-01-01']->status);
        $this->assertSame(InstalmentStatus::DUE, $byMonth['2027-02-01']->status);
        $this->assertSame(InstalmentStatus::DUE, $byMonth['2027-03-01']->status);
        $this->assertSame('10000.00', $byMonth['2027-03-01']->amount);
        $this->assertSame('30000.00', $this->svc->outstanding($loan));
    }

    public function test_grace_needs_a_due_instalment_and_a_reason(): void
    {
        $e = $this->paid();
        $by = $this->user('payroll-officer', $e);
        $loan = $this->svc->request($e, [
            'loan_type_code' => 'STAFF',
            'amount' => '30000.00',
            'instalments' => 3,
            'first_month' => '2026-12-01',
        ], $by);
        $loan = $this->approve($loan, $e);
        $this->svc->markPaid($loan, '2026-11-20', null, $by);

        try {
            $this->svc->grace($loan->fresh(), '2027-01-01', 'no', $by);
            $this->fail();
        } catch (InvalidLoan) {
            // short reason
        }

        $this->expectException(InvalidLoan::class);
        $this->svc->grace($loan->fresh(), '2028-01-01', 'Medical expenses', $by);
    }

    public function test_outstanding_is_the_sum_of_due_instalments(): void
    {
        $e = $this->paid();
        $by = $this->user('payroll-officer', $e);
        $loan = $this->svc->request($e, [
            'loan_type_code' => 'STAFF',
            'amount' => '30000.00',
            'instalments' => 3,
            'first_month' => '2026-12-01',
        ], $by);
        $loan = $this->approve($loan, $e);
        $this->svc->markPaid($loan, '2026-11-20', null, $by);

        $first = $loan->fresh()->instalmentRows()->where('sequence', 1)->first();
        $first->update(['status' => InstalmentStatus::RECOVERED]);

        $this->assertSame('20000.00', $this->svc->outstanding($loan->fresh()));
    }

    public function test_first_month_cannot_be_in_the_past(): void
    {
        $e = $this->paid();
        $by = $this->user('payroll-officer', $e);

        $this->expectException(InvalidLoan::class);
        $this->svc->request($e, [
            'loan_type_code' => 'STAFF',
            'amount' => '10000.00',
            'instalments' => 2,
            'first_month' => '2026-10-01',
        ], $by);
    }

    public function test_loan_numbers_are_unique_per_company_and_year(): void
    {
        $e = $this->paid();
        $by = $this->user('payroll-officer', $e);

        $l1 = $this->svc->request($e, [
            'loan_type_code' => 'STAFF',
            'amount' => '10000.00',
            'instalments' => 2,
            'first_month' => '2026-12-01',
        ], $by);
        $l2 = $this->svc->request($e, [
            'loan_type_code' => 'UNIFORM',
            'amount' => '5000.00',
            'instalments' => 2,
            'first_month' => '2026-12-01',
        ], $by);

        $this->assertSame('LN-2026-0001', $l1->loan_no);
        $this->assertSame('LN-2026-0002', $l2->loan_no);
    }

    public function test_cancel_removes_an_unpaid_loan_and_its_schedule(): void
    {
        $e = $this->paid();
        $by = $this->user('payroll-officer', $e);
        $loan = $this->svc->request($e, [
            'loan_type_code' => 'STAFF',
            'amount' => '30000.00',
            'instalments' => 3,
            'first_month' => '2026-12-01',
        ], $by);
        $loan = $this->approve($loan, $e);
        $this->assertCount(3, $loan->instalmentRows);

        $this->svc->cancel($loan, $by);

        $this->assertSame(LoanStatus::CANCELLED, $loan->fresh()->status);
        $this->assertSame(0, LoanInstalment::query()->where('loan_id', $loan->id)->count());
    }
}
