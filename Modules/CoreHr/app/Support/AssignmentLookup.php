<?php

namespace Modules\CoreHr\Support;

use Carbon\CarbonImmutable;
use Modules\CoreHr\Models\Assignment;
use Modules\CoreHr\Models\Employment;

/** Finds the assignment a screen should show for an employment (read only). */
final class AssignmentLookup
{
    /**
     * Today's assignment. Falls back to the latest one for exited employees and
     * to the first one for employees whose hire date is still in the future.
     */
    public static function display(Employment $employment): ?Assignment
    {
        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();

        $query = fn () => $employment->assignments()->with(['orgUnit.parent', 'job', 'grade', 'location', 'manager.person']);

        return $query()->effectiveOn($today)->first()
            ?? $query()->orderByDesc('effective_from')->first();
    }
}
