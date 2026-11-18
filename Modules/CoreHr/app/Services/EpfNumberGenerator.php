<?php

namespace Modules\CoreHr\Services;

use Modules\Organisation\Models\Company;

final class EpfNumberGenerator
{
    /**
     * Call inside the caller's DB transaction so the number and the row that uses it commit together.
     * Locks the company row FOR UPDATE, takes next_epf_seq as a numeric string, skips while $isTaken
     * is true, stores next_epf_seq = used seq + 1, returns the number.
     */
    public function next(Company $company, ?\Closure $isTaken = null): string
    {
        $locked = Company::query()
            ->whereKey($company->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $seq = (int) $locked->next_epf_seq;

        do {
            $candidate = (string) $seq;
            $taken = $isTaken !== null && $isTaken($candidate);
            if ($taken) {
                $seq++;
            }
        } while ($taken);

        $locked->forceFill(['next_epf_seq' => $seq + 1])->save();

        return (string) $seq;
    }

    /** Preview of the next EPF number without locking or consuming the sequence. */
    public function peek(Company $company): string
    {
        return (string) (int) $company->next_epf_seq;
    }
}
