<?php

namespace Modules\CoreHr\Readiness\Checks;

use Carbon\CarbonImmutable;
use Modules\CoreHr\Readiness\Severity;
use Modules\Organisation\Models\Company;

final class Hr004NoBankAccount extends AbstractCheck
{
    public function code(): string
    {
        return 'HR004';
    }

    public function title(): string
    {
        return 'No bank account';
    }

    public function severity(): Severity
    {
        return Severity::BLOCKER;
    }

    public function failing(Company $company, CarbonImmutable $asOf): array
    {
        $day = $asOf->toDateString();

        return $this->ids(
            $this->population($company, $asOf)
                ->whereNotExists(function ($q) use ($day) {
                    $q->selectRaw('1')
                        ->from('employee_bank_accounts')
                        ->whereColumn('employee_bank_accounts.employment_id', 'employments.id')
                        ->whereDate('employee_bank_accounts.effective_from', '<=', $day)
                        ->where(function ($inner) use ($day) {
                            $inner->whereNull('employee_bank_accounts.effective_to')
                                ->orWhereDate('employee_bank_accounts.effective_to', '>=', $day);
                        });
                })
        );
    }
}
