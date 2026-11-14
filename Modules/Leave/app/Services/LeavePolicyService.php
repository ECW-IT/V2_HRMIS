<?php

namespace Modules\Leave\Services;

use Carbon\Carbon;
use Modules\CoreHr\Models\Employment;
use Modules\Leave\Models\LeavePolicy;

final class LeavePolicyService
{
    /** The policy that applies to the employment for the leave type on the date, or null. */
    public function resolve(Employment $e, int $leaveTypeId, \DateTimeInterface|string $date): ?LeavePolicy
    {
        $day = Carbon::parse($date, config('app.timezone'))->toDateString();
        $assignment = $e->assignmentOn($day);
        $gradeId = $assignment?->grade_id;
        $employmentType = $e->type?->value ?? (string) $e->type;

        $candidates = LeavePolicy::query()
            ->where('leave_type_id', $leaveTypeId)
            ->whereDate('effective_from', '<=', $day)
            ->where(function ($q) use ($day) {
                $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $day);
            })
            ->get()
            ->filter(function (LeavePolicy $policy) use ($e, $gradeId, $employmentType) {
                if ($policy->company_id !== null && (int) $policy->company_id !== (int) $e->company_id) {
                    return false;
                }
                if ($policy->grade_id !== null && (int) $policy->grade_id !== (int) $gradeId) {
                    return false;
                }
                if ($policy->employment_type !== null && $policy->employment_type !== $employmentType) {
                    return false;
                }

                return true;
            });

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->sort(function (LeavePolicy $a, LeavePolicy $b) {
                $scoreA = ($a->company_id !== null ? 1 : 0)
                    + ($a->grade_id !== null ? 1 : 0)
                    + ($a->employment_type !== null ? 1 : 0);
                $scoreB = ($b->company_id !== null ? 1 : 0)
                    + ($b->grade_id !== null ? 1 : 0)
                    + ($b->employment_type !== null ? 1 : 0);

                if ($scoreA !== $scoreB) {
                    return $scoreB <=> $scoreA;
                }

                return Carbon::parse($b->effective_from)->timestamp <=> Carbon::parse($a->effective_from)->timestamp;
            })
            ->first();
    }
}
