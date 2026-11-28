<?php

namespace Modules\Payroll\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Enums\AdvanceStatus;
use Modules\Payroll\Enums\InstalmentStatus;
use Modules\Payroll\Enums\LoanStatus;
use Modules\Payroll\Exceptions\InvalidAdvance;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\LoanType;
use Modules\Payroll\Models\SalaryAdvance;
use Modules\Workflow\Enums\ApprovalActionType;
use Modules\Workflow\Services\ApprovalService;

final class AdvanceService
{
    public function __construct(
        private readonly CompensationService $compensation,
        private readonly LoanService $loans,
        private readonly ApprovalService $approvals,
    ) {}

    public function request(Employment $e, PayPeriod $p, string $amount, ?string $reason, User $by): SalaryAdvance
    {
        $this->authorizeManage($by, (int) $e->company_id);
        $this->refuseSelf($by, $e);
        $this->assertSameCompany($e, $p);
        $this->assertPeriodAcceptsAdvances($p);
        $this->assertEmployedInPeriod($e, $p);

        $status = $e->status instanceof EmploymentStatus ? $e->status : EmploymentStatus::from((string) $e->status);
        if ($status !== EmploymentStatus::ACTIVE) {
            throw new InvalidAdvance('The employee must be active.');
        }

        $amount = $this->money($amount);
        if (bccomp($amount, '0.00', 2) <= 0) {
            throw new InvalidAdvance('Advance amount must be greater than zero.');
        }

        $this->assertWithinLimit($e, $p, $amount);

        $advance = SalaryAdvance::query()->create([
            'company_id' => $e->company_id,
            'employment_id' => $e->id,
            'pay_period_id' => $p->id,
            'amount' => $amount,
            'requested_on' => now(config('app.timezone'))->toDateString(),
            'reason' => $reason,
            'status' => AdvanceStatus::PENDING,
            'approval_request_id' => null,
            'created_by' => $by->id,
        ]);

        $request = $this->approvals->submit(
            'SALARY_ADVANCE',
            (int) $e->company_id,
            SalaryAdvance::class,
            $advance->id,
            ApprovalActionType::CREATE,
            ['amount' => $amount, 'reason' => $reason, 'pay_period_id' => $p->id],
            $by,
            $e->id,
        );

        $advance->update(['approval_request_id' => $request->id]);

        return $advance->fresh();
    }

    public function markPaid(SalaryAdvance $a, string $paidOn, ?string $reference, User $by): SalaryAdvance
    {
        $this->authorizePay($by, (int) $a->company_id);

        if ($a->status !== AdvanceStatus::APPROVED) {
            throw new InvalidAdvance('Only an approved advance can be marked paid.');
        }

        $paidOn = trim($this->dateString($paidOn));
        if ($paidOn === '') {
            throw new InvalidAdvance('A payment date is required.');
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $paidOn, config('app.timezone'));
        } catch (\Throwable) {
            throw new InvalidAdvance('A payment date is required.');
        }

        if ($parsed === false || $parsed->format('Y-m-d') !== $paidOn) {
            throw new InvalidAdvance('A payment date is required.');
        }

        $paidOn = $parsed->toDateString();
        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();
        if ($paidOn > $today) {
            throw new InvalidAdvance('Payment date cannot be in the future.');
        }

        $a->update([
            'status' => AdvanceStatus::PAID,
            'paid_on' => $paidOn,
            'paid_by' => $by->id,
            'payment_reference' => $reference,
        ]);

        return $a->fresh();
    }

    public function cancel(SalaryAdvance $a, User $by): SalaryAdvance
    {
        $this->authorizeManage($by, (int) $a->company_id);

        if (! in_array($a->status, [AdvanceStatus::PENDING, AdvanceStatus::APPROVED], true)) {
            throw new InvalidAdvance('Only a pending or approved advance can be cancelled.');
        }

        $a->update(['status' => AdvanceStatus::CANCELLED]);

        return $a->fresh();
    }

    /** PAID advances of the employment to recover in the period, oldest first. Includes PAID advances of earlier periods never recovered. */
    public function dueFor(int $employmentId, PayPeriod $p): Collection
    {
        $periodStart = $this->dateString($p->start_date);

        return SalaryAdvance::query()
            ->where('employment_id', $employmentId)
            ->where('status', AdvanceStatus::PAID)
            ->whereNull('recovered_pay_run_id')
            ->whereHas('payPeriod', function ($q) use ($periodStart) {
                $q->whereDate('start_date', '<=', $periodStart);
            })
            ->orderBy('paid_on')
            ->orderBy('id')
            ->get();
    }

    /** Turns a PAID advance into a loan of the same amount. */
    public function convertToLoan(SalaryAdvance $a, int $instalments, string $firstMonth, User $by): Loan
    {
        $this->authorizeLoanManage($by, (int) $a->company_id);

        if ($a->status !== AdvanceStatus::PAID) {
            throw new InvalidAdvance('Only a paid advance can be converted to a loan.');
        }

        return DB::transaction(function () use ($a, $instalments, $firstMonth, $by) {
            $employment = Employment::query()->findOrFail($a->employment_id);
            $type = LoanType::query()->where('code', 'STAFF')->firstOrFail();
            $firstMonth = $this->dateString($firstMonth);

            $loan = Loan::query()->create([
                'company_id' => $a->company_id,
                'employment_id' => $a->employment_id,
                'loan_type_id' => $type->id,
                'loan_no' => $this->loans->nextLoanNo((int) $a->company_id, CarbonImmutable::parse($firstMonth)->year),
                'amount' => $a->amount,
                'instalments' => $instalments,
                'first_month' => $firstMonth,
                'reason' => $a->reason,
                'status' => LoanStatus::ACTIVE,
                'approval_request_id' => null,
                'paid_on' => $this->dateString($a->paid_on),
                'payment_reference' => $a->payment_reference,
                'created_by' => $by->id,
            ]);

            foreach ($this->loans->schedule((string) $a->amount, $instalments, $firstMonth) as $row) {
                $loan->instalmentRows()->create([
                    'sequence' => $row['sequence'],
                    'due_month' => $row['due_month'],
                    'amount' => $row['amount'],
                    'status' => InstalmentStatus::DUE,
                    'pay_run_id' => null,
                    'note' => null,
                ]);
            }

            $a->update([
                'status' => AdvanceStatus::CONVERTED,
                'converted_loan_id' => $loan->id,
            ]);

            return $loan->fresh(['instalmentRows']);
        });
    }

    /** APPROVED advances not yet paid, for the pre-run warning. */
    public function approvedUnpaidCount(PayPeriod $p): int
    {
        return SalaryAdvance::query()
            ->where('pay_period_id', $p->id)
            ->where('status', AdvanceStatus::APPROVED)
            ->count();
    }

    public function markApproved(SalaryAdvance $a, int $approvalRequestId): void
    {
        $a->update([
            'status' => AdvanceStatus::APPROVED,
            'approval_request_id' => $approvalRequestId,
        ]);
    }

    public function markRejected(SalaryAdvance $a): void
    {
        if ($a->status === AdvanceStatus::PENDING) {
            $a->update(['status' => AdvanceStatus::REJECTED]);
        }
    }

    /** Opening import: create a PAID advance for the given period (unrecovered). */
    public function loadOpening(Employment $e, PayPeriod $p, string $amount, User $by, ?string $paidOn = null): SalaryAdvance
    {
        $this->authorizeManage($by, (int) $e->company_id);
        $this->assertSameCompany($e, $p);

        $amount = $this->money($amount);
        if (bccomp($amount, '0.00', 2) <= 0) {
            throw new InvalidAdvance('Advance amount must be greater than zero.');
        }

        $paidOn = $this->dateString($paidOn ?? $p->start_date);

        return SalaryAdvance::query()->create([
            'company_id' => $e->company_id,
            'employment_id' => $e->id,
            'pay_period_id' => $p->id,
            'amount' => $amount,
            'requested_on' => $paidOn,
            'reason' => 'Opening data from legacy payroll',
            'status' => AdvanceStatus::PAID,
            'approval_request_id' => null,
            'paid_on' => $paidOn,
            'paid_by' => $by->id,
            'payment_reference' => null,
            'recovered_pay_run_id' => null,
            'converted_loan_id' => null,
            'created_by' => $by->id,
        ]);
    }

    private function assertWithinLimit(Employment $e, PayPeriod $p, string $amount): void
    {
        $comp = $this->compensation->on($e, $this->dateString($p->end_date));
        $basic = $comp !== null ? $this->money($comp->basic_salary) : '0.00';

        if (bccomp($basic, '0.00', 2) === 0) {
            return;
        }

        $percent = (string) config('payroll.advance_limit_percent', 50);
        $limit = bcmul($basic, bcdiv($percent, '100', 6), 2);

        $existing = SalaryAdvance::query()
            ->where('employment_id', $e->id)
            ->where('pay_period_id', $p->id)
            ->whereIn('status', [
                AdvanceStatus::PENDING,
                AdvanceStatus::APPROVED,
                AdvanceStatus::PAID,
            ])
            ->sum('amount');

        $total = bcadd($this->money($existing), $amount, 2);
        if (bccomp($total, $limit, 2) > 0) {
            throw new InvalidAdvance('Advances for this period exceed half of basic salary.');
        }
    }

    private function assertPeriodAcceptsAdvances(PayPeriod $p): void
    {
        if (app(PayRunService::class)->isPeriodFrozen((int) $p->id)) {
            throw new InvalidAdvance('Advances are closed for this period.');
        }
    }

    private function assertEmployedInPeriod(Employment $e, PayPeriod $p): void
    {
        $start = $this->dateString($p->start_date);
        $end = $this->dateString($p->end_date);
        $hire = $this->dateString($e->hire_date);
        $exit = $e->exit_date !== null ? $this->dateString($e->exit_date) : null;

        if ($hire > $end || ($exit !== null && $exit < $start)) {
            throw new InvalidAdvance('The employee was not employed in this period.');
        }
    }

    private function assertSameCompany(Employment $e, PayPeriod $p): void
    {
        if ((int) $e->company_id !== (int) $p->company_id) {
            throw new InvalidAdvance('Employment and pay period must belong to the same company.');
        }
    }

    private function refuseSelf(User $by, Employment $e): void
    {
        if ($by->person_id !== null && (int) $by->person_id === (int) $e->person_id) {
            throw new AuthorizationException('You cannot request an advance for yourself.');
        }
    }

    private function authorizeManage(User $by, int $companyId): void
    {
        if (! $by->can('advance.manage') || ! $this->canAccessCompany($by, $companyId)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    private function authorizePay(User $by, int $companyId): void
    {
        if (! $by->can('advance.pay') || ! $this->canAccessCompany($by, $companyId)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    private function authorizeLoanManage(User $by, int $companyId): void
    {
        if (! $by->can('loan.manage') || ! $this->canAccessCompany($by, $companyId)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    private function canAccessCompany(User $by, int $companyId): bool
    {
        if ($by->all_companies) {
            return true;
        }

        return $by->companies()->where('companies.id', $companyId)->exists();
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function dateString(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }
}
