<?php

namespace Modules\CoreHr\Services;

use Modules\Organisation\Models\Company;

final class EmployeeNumberGenerator
{
    /**
     * Call it inside the caller's DB transaction, so the number and the row that uses it commit together.
     * Locks the company row FOR UPDATE, builds prefix + str_pad(seq, padding, '0', STR_PAD_LEFT),
     * skips forward while $isTaken($candidate) is true, stores next_employee_seq = used seq + 1, returns the number.
     */
    public function next(Company $company, ?\Closure $isTaken = null): string
    {
        $locked = Company::query()
            ->whereKey($company->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $prefix = (string) $locked->employee_no_prefix;
        $padding = (int) $locked->employee_no_padding;
        $seq = (int) $locked->next_employee_seq;

        do {
            $candidate = $prefix.str_pad((string) $seq, $padding, '0', STR_PAD_LEFT);
            $taken = $isTaken !== null && $isTaken($candidate);
            if ($taken) {
                $seq++;
            }
        } while ($taken);

        $locked->forceFill(['next_employee_seq' => $seq + 1])->save();

        return $candidate;
    }

    /** Upper-case; remove spaces, underscores, hyphens and dots. */
    public static function normalise(string $raw): string
    {
        $upper = mb_strtoupper($raw);

        return str_replace([' ', '_', '-', '.'], '', $upper);
    }

    /** Trailing digits of a normalised number as int, or null when there are none. 'ECW035' -> 35. */
    public static function sequenceOf(string $normalised): ?int
    {
        if (! preg_match('/(\d+)$/', $normalised, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }
}
