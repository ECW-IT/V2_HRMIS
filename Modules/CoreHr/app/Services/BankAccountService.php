<?php

namespace Modules\CoreHr\Services;

use App\Support\Effective\EffectiveDating;
use Modules\CoreHr\Exceptions\InvalidBankAccount;
use Modules\CoreHr\Models\BankBranch;
use Modules\CoreHr\Models\EmployeeBankAccount;
use Modules\CoreHr\Models\Employment;

final class BankAccountService
{
    /** $data: effective_from, is_cash?, bank_id?, bank_branch_id?, account_no?, account_name? */
    public function set(Employment $e, array $data): EmployeeBankAccount
    {
        $from = $this->dateString($data['effective_from'] ?? null);
        if ($from === '' || $from < $this->dateString($e->hire_date)) {
            throw new InvalidBankAccount('Bank account effective date is before the hire date.');
        }

        $isCash = (bool) ($data['is_cash'] ?? false);

        if ($isCash) {
            $payload = [
                'employment_id' => $e->id,
                'effective_from' => $from,
                'is_cash' => true,
                'bank_id' => null,
                'bank_branch_id' => null,
                'account_no' => null,
                'account_name' => null,
            ];
        } else {
            foreach (['bank_id', 'bank_branch_id', 'account_no', 'account_name'] as $required) {
                if (! array_key_exists($required, $data) || $data[$required] === null || $data[$required] === '') {
                    throw new InvalidBankAccount('Bank, branch, account number and account name are required.');
                }
            }

            $branch = BankBranch::query()->find($data['bank_branch_id']);
            if ($branch === null || (int) $branch->bank_id !== (int) $data['bank_id']) {
                throw new InvalidBankAccount('Branch must belong to the selected bank.');
            }

            $accountNo = preg_replace('/\s+/', '', trim((string) $data['account_no'])) ?? '';
            if (preg_match('/^\d{6,20}$/', $accountNo) !== 1) {
                throw new InvalidBankAccount('Account number must be 6 to 20 digits.');
            }

            $payload = [
                'employment_id' => $e->id,
                'effective_from' => $from,
                'is_cash' => false,
                'bank_id' => $data['bank_id'],
                'bank_branch_id' => $data['bank_branch_id'],
                'account_no' => $accountNo,
                'account_name' => $data['account_name'],
            ];
        }

        /** @var EmployeeBankAccount $saved */
        $saved = EffectiveDating::insert(new EmployeeBankAccount($payload));

        return $saved->fresh();
    }

    public function on(Employment $e, \DateTimeInterface|string $date): ?EmployeeBankAccount
    {
        return EmployeeBankAccount::query()
            ->where('employment_id', $e->id)
            ->effectiveOn($date)
            ->first();
    }

    private function dateString(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
