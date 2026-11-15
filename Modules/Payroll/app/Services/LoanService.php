<?php

namespace Modules\Payroll\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Enums\InstalmentStatus;
use Modules\Payroll\Enums\LoanStatus;
use Modules\Payroll\Exceptions\InvalidLoan;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\LoanInstalment;
use Modules\Payroll\Models\LoanType;
use Modules\Workflow\Enums\ApprovalActionType;
use Modules\Workflow\Services\ApprovalService;

final class LoanService
{
    public function __construct(private readonly ApprovalService $approvals) {}

    /** $data keys: loan_type_code, amount, instalments, first_month, reason?. */
    public function request(Employment $e, array $data, User $by): Loan
    {
        $this->authorizeManage($by, (int) $e->company_id);
        $this->refuseSelf($by, $e);

        $amount = $this->money($data['amount'] ?? null);
        if (bccomp($amount, '0.00', 2) <= 0) {
            throw new InvalidLoan('Loan amount must be greater than zero.');
        }

        $instalments = (int) ($data['instalments'] ?? 0);
        if ($instalments < 1 || $instalments > 60) {
            throw new InvalidLoan('Instalments must be between 1 and 60.');
        }

        $firstMonth = $this->dateString($data['first_month'] ?? '');
        $this->assertFirstMonth($firstMonth);
        $this->assertPeriodNotLockedForMonth($firstMonth);

        $type = LoanType::query()->where('code', (string) ($data['loan_type_code'] ?? ''))->where('is_active', true)->first();
        if ($type === null) {
            throw new InvalidLoan('Unknown loan type.');
        }

        $year = CarbonImmutable::parse($firstMonth)->year;

        return DB::transaction(function () use ($e, $type, $amount, $instalments, $firstMonth, $data, $by, $year) {
            $loan = Loan::query()->create([
                'company_id' => $e->company_id,
                'employment_id' => $e->id,
                'loan_type_id' => $type->id,
                'loan_no' => $this->nextLoanNo((int) $e->company_id, $year),
                'amount' => $amount,
                'instalments' => $instalments,
                'first_month' => $firstMonth,
                'reason' => $data['reason'] ?? null,
                'status' => LoanStatus::PENDING,
                'approval_request_id' => null,
                'created_by' => $by->id,
            ]);

            $request = $this->approvals->submit(
                'LOAN',
                (int) $e->company_id,
                Loan::class,
                $loan->id,
                ApprovalActionType::CREATE,
                [
                    'loan_type_code' => $type->code,
                    'amount' => $amount,
                    'instalments' => $instalments,
                    'first_month' => $firstMonth,
                    'reason' => $data['reason'] ?? null,
                ],
                $by,
                $e->id,
            );

            $loan->update(['approval_request_id' => $request->id]);

            return $loan->fresh();
        });
    }

    public function markPaid(Loan $l, string $paidOn, ?string $reference, User $by): Loan
    {
        $this->authorizePay($by, (int) $l->company_id);

        if ($l->status !== LoanStatus::APPROVED) {
            throw new InvalidLoan('Only an approved loan can be marked paid.');
        }

        $paidOn = $this->dateString($paidOn);
        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();
        if ($paidOn > $today) {
            throw new InvalidLoan('Payment date cannot be in the future.');
        }

        $l->update([
            'status' => LoanStatus::ACTIVE,
            'paid_on' => $paidOn,
            'payment_reference' => $reference,
        ]);

        return $l->fresh();
    }

    public function cancel(Loan $l, User $by): Loan
    {
        $this->authorizeManage($by, (int) $l->company_id);

        if (! in_array($l->status, [LoanStatus::PENDING, LoanStatus::APPROVED], true)) {
            throw new InvalidLoan('Only a pending or approved loan can be cancelled.');
        }

        return DB::transaction(function () use ($l) {
            $l->instalmentRows()->delete();
            $l->update(['status' => LoanStatus::CANCELLED]);

            return $l->fresh();
        });
    }

    /** Builds the schedule: equal instalments, the remainder on the last. */
    public function schedule(string $amount, int $instalments, string $firstMonth): array
    {
        $amount = $this->money($amount);
        $firstMonth = $this->dateString($firstMonth);
        $base = bcdiv($amount, (string) $instalments, 2); // floor via bcdiv scale 2
        $rows = [];
        $allocated = '0.00';
        $cursor = CarbonImmutable::parse($firstMonth)->startOfMonth();

        for ($i = 1; $i <= $instalments; $i++) {
            if ($i === $instalments) {
                $rowAmount = bcsub($amount, $allocated, 2);
            } else {
                $rowAmount = $base;
                $allocated = bcadd($allocated, $base, 2);
            }

            $rows[] = [
                'sequence' => $i,
                'due_month' => $cursor->toDateString(),
                'amount' => $rowAmount,
            ];
            $cursor = $cursor->addMonthNoOverflow();
        }

        return $rows;
    }

    /** Grace month: the DUE instalment of that month becomes MOVED and a new DUE instalment of the same amount is added one month after the last. */
    public function grace(Loan $l, string $month, string $reason, User $by): Loan
    {
        $this->authorizeManage($by, (int) $l->company_id);

        if (mb_strlen(trim($reason)) < 5) {
            throw new InvalidLoan('A reason of at least 5 characters is required.');
        }

        $month = CarbonImmutable::parse($this->dateString($month))->startOfMonth()->toDateString();
        $this->assertPeriodNotLockedForMonth($month);

        $instalment = $l->instalmentRows()
            ->whereDate('due_month', $month)
            ->where('status', InstalmentStatus::DUE)
            ->first();

        if ($instalment === null) {
            throw new InvalidLoan('There is no due instalment for that month.');
        }

        return DB::transaction(function () use ($l, $instalment, $reason) {
            $instalment->update([
                'status' => InstalmentStatus::MOVED,
                'note' => $reason,
            ]);

            $last = $l->instalmentRows()->orderByDesc('due_month')->orderByDesc('sequence')->first();
            $nextMonth = CarbonImmutable::parse($this->dateString($last->due_month))->addMonthNoOverflow()->toDateString();
            $nextSequence = ((int) $l->instalmentRows()->max('sequence')) + 1;

            $l->instalmentRows()->create([
                'sequence' => $nextSequence,
                'due_month' => $nextMonth,
                'amount' => $instalment->amount,
                'status' => InstalmentStatus::DUE,
                'pay_run_id' => null,
                'note' => null,
            ]);

            return $l->fresh(['instalmentRows']);
        });
    }

    /** DUE instalments of ACTIVE loans of the employment with due_month on or before the period's month. */
    public function dueFor(int $employmentId, PayPeriod $p): Collection
    {
        $monthEnd = CarbonImmutable::parse($this->dateString($p->start_date))->startOfMonth()->toDateString();

        return LoanInstalment::query()
            ->where('status', InstalmentStatus::DUE)
            ->whereDate('due_month', '<=', $monthEnd)
            ->whereHas('loan', function ($q) use ($employmentId) {
                $q->where('employment_id', $employmentId)
                    ->where('status', LoanStatus::ACTIVE);
            })
            ->orderBy('due_month')
            ->orderBy('sequence')
            ->get();
    }

    public function outstanding(Loan $l): string
    {
        $sum = $l->instalmentRows()
            ->where('status', InstalmentStatus::DUE)
            ->sum('amount');

        return $this->money($sum);
    }

    public function markApproved(Loan $l, int $approvalRequestId): void
    {
        DB::transaction(function () use ($l, $approvalRequestId) {
            $l->update([
                'status' => LoanStatus::APPROVED,
                'approval_request_id' => $approvalRequestId,
            ]);

            foreach ($this->schedule((string) $l->amount, (int) $l->instalments, $this->dateString($l->first_month)) as $row) {
                $l->instalmentRows()->create([
                    'sequence' => $row['sequence'],
                    'due_month' => $row['due_month'],
                    'amount' => $row['amount'],
                    'status' => InstalmentStatus::DUE,
                    'pay_run_id' => null,
                    'note' => null,
                ]);
            }
        });
    }

    public function markRejected(Loan $l): void
    {
        if ($l->status === LoanStatus::PENDING) {
            $l->update(['status' => LoanStatus::REJECTED]);
        }
    }

    public function nextLoanNo(int $companyId, int $year): string
    {
        $prefix = 'LN-'.$year.'-';

        $latest = Loan::query()
            ->where('company_id', $companyId)
            ->where('loan_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('loan_no')
            ->value('loan_no');

        $seq = 1;
        if ($latest !== null && preg_match('/^LN-\d{4}-(\d+)$/', $latest, $m) === 1) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function assertFirstMonth(string $firstMonth): void
    {
        $parsed = CarbonImmutable::parse($firstMonth);
        if ($parsed->day !== 1) {
            throw new InvalidLoan('First month must be the first day of a month.');
        }

        $currentMonth = CarbonImmutable::now(config('app.timezone'))->startOfMonth();
        if ($parsed->lt($currentMonth)) {
            throw new InvalidLoan('First month cannot be in the past.');
        }
    }

    /** Stub for Task 33. */
    private function assertPeriodNotLockedForMonth(string $month): void
    {
        // Task 33 will enforce locked pay runs.
    }

    private function refuseSelf(User $by, Employment $e): void
    {
        if ($by->person_id !== null && (int) $by->person_id === (int) $e->person_id) {
            throw new AuthorizationException('You cannot request a loan for yourself.');
        }
    }

    private function authorizeManage(User $by, int $companyId): void
    {
        if (! $by->can('loan.manage') || ! $this->canAccessCompany($by, $companyId)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    private function authorizePay(User $by, int $companyId): void
    {
        if (! $by->can('advance.pay') || ! $this->canAccessCompany($by, $companyId)) {
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
