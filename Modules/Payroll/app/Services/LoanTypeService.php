<?php

namespace Modules\Payroll\Services;

use Illuminate\Support\Facades\Gate;
use Modules\Payroll\Exceptions\InvalidLoan;
use Modules\Payroll\Models\LoanType;

final class LoanTypeService
{
    public function create(string $code, string $name): LoanType
    {
        Gate::authorize('payroll.config');

        $code = strtoupper(trim($code));
        $name = trim($name);

        if ($code === '' || $name === '') {
            throw new InvalidLoan('Enter a code and a name.');
        }

        if (LoanType::query()->where('code', $code)->exists()) {
            throw new InvalidLoan('That loan type code is already in use.');
        }

        return LoanType::query()->create([
            'code' => $code,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    public function update(LoanType $type, string $name, bool $active): LoanType
    {
        Gate::authorize('payroll.config');

        $name = trim($name);
        if ($name === '') {
            throw new InvalidLoan('Enter a name.');
        }

        $type->update([
            'name' => $name,
            'is_active' => $active,
        ]);

        return $type->fresh();
    }
}
