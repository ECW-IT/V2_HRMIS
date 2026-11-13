<?php

namespace Modules\CoreHr\Livewire\Concerns;

use Modules\CoreHr\Models\Bank;
use Modules\CoreHr\Models\BankBranch;

/** For components with `bankId` and `bankBranchId` properties. */
trait LoadsBankOptions
{
    public function updatedBankId(): void
    {
        // A branch only makes sense for its own bank.
        if ($this->bankBranchId === null || $this->bankBranchId === '') {
            return;
        }

        $belongs = BankBranch::query()
            ->whereKey((int) $this->bankBranchId)
            ->where('bank_id', (int) $this->bankId)
            ->exists();

        if (! $belongs) {
            $this->bankBranchId = null;
        }
    }

    /** @return array{banks: mixed, branches: mixed} */
    protected function bankOptions(): array
    {
        $bankId = $this->bankId === null || $this->bankId === '' ? null : (int) $this->bankId;

        return [
            'banks' => Bank::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => $bankId === null
                ? collect()
                : BankBranch::query()
                    ->where('bank_id', $bankId)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'code']),
        ];
    }
}
