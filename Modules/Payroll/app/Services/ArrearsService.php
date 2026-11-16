<?php

namespace Modules\Payroll\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Enums\ArrearsKind;
use Modules\Payroll\Exceptions\InvalidLedgerEntry;
use Modules\Payroll\Models\ArrearsEntry;

final class ArrearsService
{
    /** Sum of all entries. Never negative in normal use. */
    public function balance(int $employmentId): string
    {
        $sum = ArrearsEntry::query()->where('employment_id', $employmentId)->sum('amount');

        return $this->money($sum);
    }

    public function entries(int $employmentId): Collection
    {
        return ArrearsEntry::query()
            ->where('employment_id', $employmentId)
            ->orderBy('id')
            ->get();
    }

    /** Called by the pay run on lock. $amount > 0. */
    public function recordShortfall(int $employmentId, int $payRunId, int $payPeriodId, string $amount): ArrearsEntry
    {
        $amount = $this->positiveAmount($amount);
        $this->assertOnePerRun($employmentId, $payRunId, ArrearsKind::SHORTFALL);

        $employment = Employment::query()->findOrFail($employmentId);

        return $this->insert($employment, ArrearsKind::SHORTFALL, $amount, $payRunId, $payPeriodId, null, null);
    }

    /** Called by the pay run on lock. $amount > 0; stored negative. */
    public function recordRecovery(int $employmentId, int $payRunId, int $payPeriodId, string $amount): ArrearsEntry
    {
        $amount = $this->positiveAmount($amount);
        $this->assertOnePerRun($employmentId, $payRunId, ArrearsKind::RECOVERY);

        $balance = $this->balance($employmentId);
        if (bccomp($amount, $balance, 2) > 0) {
            throw new InvalidLedgerEntry('Recovery cannot exceed the arrears balance.');
        }

        $employment = Employment::query()->findOrFail($employmentId);

        return $this->insert(
            $employment,
            ArrearsKind::RECOVERY,
            bcmul($amount, '-1', 2),
            $payRunId,
            $payPeriodId,
            null,
            null,
        );
    }

    public function opening(Employment $e, string $amount, string $note, User $by): ArrearsEntry
    {
        $this->authorizeAdjust($by, (int) $e->company_id);
        $this->assertReason($note);

        if (ArrearsEntry::query()->where('employment_id', $e->id)->exists()) {
            throw new InvalidLedgerEntry('Opening is only allowed on an empty arrears ledger.');
        }

        $amount = $this->positiveAmount($amount);

        return $this->insert($e, ArrearsKind::OPENING, $amount, null, null, $note, $by->id);
    }

    public function adjust(Employment $e, string $signedAmount, string $reason, User $by): ArrearsEntry
    {
        $this->authorizeAdjust($by, (int) $e->company_id);
        $this->assertReason($reason);

        $signedAmount = $this->money($signedAmount);
        if (bccomp($signedAmount, '0.00', 2) === 0) {
            throw new InvalidLedgerEntry('Adjustment amount cannot be zero.');
        }

        if (bccomp($signedAmount, '0.00', 2) < 0) {
            $this->assertBalanceNotBelowZero($e->id, $signedAmount);
        }

        return $this->insert($e, ArrearsKind::ADJUSTMENT, $signedAmount, null, null, $reason, $by->id);
    }

    public function writeOff(Employment $e, string $amount, string $reason, User $by): ArrearsEntry
    {
        $this->authorizeAdjust($by, (int) $e->company_id);
        $this->assertReason($reason);

        $amount = $this->positiveAmount($amount);
        $negative = bcmul($amount, '-1', 2);
        $this->assertBalanceNotBelowZero($e->id, $negative);

        return $this->insert($e, ArrearsKind::WRITE_OFF, $negative, null, null, $reason, $by->id);
    }

    /** [employment_id => balance] for employments in the company with a non-zero balance. */
    public function balances(int $companyId): array
    {
        $rows = ArrearsEntry::query()
            ->where('company_id', $companyId)
            ->selectRaw('employment_id, SUM(amount) as balance')
            ->groupBy('employment_id')
            ->havingRaw('SUM(amount) <> 0')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->employment_id] = $this->money($row->balance);
        }

        return $result;
    }

    private function insert(
        Employment $e,
        ArrearsKind $kind,
        string $amount,
        ?int $payRunId,
        ?int $payPeriodId,
        ?string $note,
        ?int $createdBy,
    ): ArrearsEntry {
        $entry = new ArrearsEntry([
            'company_id' => $e->company_id,
            'employment_id' => $e->id,
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

    private function assertOnePerRun(int $employmentId, int $payRunId, ArrearsKind $kind): void
    {
        $exists = ArrearsEntry::query()
            ->where('employment_id', $employmentId)
            ->where('pay_run_id', $payRunId)
            ->where('kind', $kind)
            ->exists();

        if ($exists) {
            throw new InvalidLedgerEntry('Only one '.$kind->value.' is allowed per employment per pay run.');
        }
    }

    private function assertBalanceNotBelowZero(int $employmentId, string $delta): void
    {
        $next = bcadd($this->balance($employmentId), $delta, 2);
        if (bccomp($next, '0.00', 2) < 0) {
            throw new InvalidLedgerEntry('Arrears balance cannot go below zero.');
        }
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
