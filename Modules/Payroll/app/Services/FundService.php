<?php

namespace Modules\Payroll\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Enums\FundCode;
use Modules\Payroll\Enums\FundEntryKind;
use Modules\Payroll\Exceptions\InvalidLedgerEntry;
use Modules\Payroll\Models\FundEntry;

final class FundService
{
    public function balance(int $employmentId, FundCode $fund): string
    {
        $sum = FundEntry::query()
            ->where('employment_id', $employmentId)
            ->where('fund', $fund)
            ->sum('amount');

        return $this->money($sum);
    }

    public function statement(int $employmentId, FundCode $fund): Collection
    {
        $entries = FundEntry::query()
            ->where('employment_id', $employmentId)
            ->where('fund', $fund)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $running = '0.00';

        return $entries->map(function (FundEntry $entry) use (&$running) {
            $running = bcadd($running, (string) $entry->amount, 2);

            return [
                'id' => $entry->id,
                'kind' => $entry->kind->value,
                'amount' => $this->money($entry->amount),
                'note' => $entry->note,
                'pay_run_id' => $entry->pay_run_id,
                'pay_period_id' => $entry->pay_period_id,
                'created_at' => $entry->created_at,
                'balance_after' => $running,
            ];
        });
    }

    /** Called by the pay run on lock. $amount > 0. */
    public function recordContribution(
        int $employmentId,
        FundCode $fund,
        int $payRunId,
        int $payPeriodId,
        string $amount,
    ): FundEntry {
        $amount = $this->positiveAmount($amount);

        $exists = FundEntry::query()
            ->where('employment_id', $employmentId)
            ->where('fund', $fund)
            ->where('pay_run_id', $payRunId)
            ->where('kind', FundEntryKind::CONTRIBUTION)
            ->exists();

        if ($exists) {
            throw new InvalidLedgerEntry('Only one contribution is allowed per fund per pay run.');
        }

        $employment = Employment::query()->findOrFail($employmentId);

        return $this->insert($employment, $fund, FundEntryKind::CONTRIBUTION, $amount, $payRunId, $payPeriodId, null, null);
    }

    public function opening(Employment $e, FundCode $fund, string $amount, string $note, User $by): FundEntry
    {
        $this->authorizeAdjust($by, (int) $e->company_id);
        $this->assertReason($note);

        if (FundEntry::query()->where('employment_id', $e->id)->where('fund', $fund)->exists()) {
            throw new InvalidLedgerEntry('Opening is only allowed on an empty fund ledger.');
        }

        $amount = $this->positiveAmount($amount);

        return $this->insert($e, $fund, FundEntryKind::OPENING, $amount, null, null, $note, $by->id);
    }

    public function refund(Employment $e, FundCode $fund, string $amount, string $reason, User $by): FundEntry
    {
        $this->authorizeAdjust($by, (int) $e->company_id);
        $this->assertReason($reason);

        // PLACEHOLDER — confirm with Finance
        if ($fund === FundCode::WELFARE) {
            throw new InvalidLedgerEntry('Welfare contributions are not refundable.');
        }

        $amount = $this->positiveAmount($amount);
        $balance = $this->balance($e->id, $fund);
        if (bccomp($amount, $balance, 2) > 0) {
            throw new InvalidLedgerEntry('Refund cannot exceed the fund balance.');
        }

        return $this->insert($e, $fund, FundEntryKind::REFUND, bcmul($amount, '-1', 2), null, null, $reason, $by->id);
    }

    public function adjust(Employment $e, FundCode $fund, string $signedAmount, string $reason, User $by): FundEntry
    {
        $this->authorizeAdjust($by, (int) $e->company_id);
        $this->assertReason($reason);

        $signedAmount = $this->money($signedAmount);
        if (bccomp($signedAmount, '0.00', 2) === 0) {
            throw new InvalidLedgerEntry('Adjustment amount cannot be zero.');
        }

        $next = bcadd($this->balance($e->id, $fund), $signedAmount, 2);
        if (bccomp($next, '0.00', 2) < 0) {
            throw new InvalidLedgerEntry('Fund balance cannot go below zero.');
        }

        return $this->insert($e, $fund, FundEntryKind::ADJUSTMENT, $signedAmount, null, null, $reason, $by->id);
    }

    private function insert(
        Employment $e,
        FundCode $fund,
        FundEntryKind $kind,
        string $amount,
        ?int $payRunId,
        ?int $payPeriodId,
        ?string $note,
        ?int $createdBy,
    ): FundEntry {
        $entry = new FundEntry([
            'company_id' => $e->company_id,
            'employment_id' => $e->id,
            'fund' => $fund,
            'kind' => $kind,
            'amount' => $amount,
            'pay_run_id' => $payRunId,
            'pay_period_id' => $payPeriodId,
            'note' => $note,
            'created_by' => $createdBy,
            'created_at' => now(),
        ]);
        $entry->save();

        return $entry->fresh();
    }

    private function authorizeAdjust(User $by, int $companyId): void
    {
        if (! $by->can('ledger.adjust') || ! $this->canAccessCompany($by, $companyId)) {
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

    private function assertReason(string $reason): void
    {
        if (mb_strlen(trim($reason)) < 5) {
            throw new InvalidLedgerEntry('A reason of at least 5 characters is required.');
        }
    }

    private function positiveAmount(string $amount): string
    {
        $amount = $this->money($amount);
        if (bccomp($amount, '0.00', 2) <= 0) {
            throw new InvalidLedgerEntry('Amount must be greater than zero.');
        }

        return $amount;
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
