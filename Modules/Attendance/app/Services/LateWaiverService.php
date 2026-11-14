<?php

namespace Modules\Attendance\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Enums\DecisionLogKind;
use Modules\Attendance\Exceptions\InvalidOtDecision;
use Modules\Attendance\Models\Timecard;
use Modules\Attendance\Models\TimecardDecisionLog;
use Modules\Calendar\Services\PayPeriodService;

final class LateWaiverService
{
    public function waive(Timecard $t, User $by, string $reason): Timecard
    {
        $this->authorize($by, $t);
        $this->assertOpenPeriod($t);

        if (mb_strlen(trim($reason)) < 5) {
            throw new InvalidOtDecision('A reason of at least 5 characters is required.');
        }

        $minutes = self::chargeable($t);
        if ($minutes <= 0) {
            throw new InvalidOtDecision('There are no late minutes to waive.');
        }

        return DB::transaction(function () use ($t, $by, $reason, $minutes) {
            $t->forceFill(['late_waived' => true])->save();

            TimecardDecisionLog::query()->create([
                'timecard_id' => $t->id,
                'employment_id' => $t->employment_id,
                'work_date' => Carbon::parse($t->work_date)->toDateString(),
                'kind' => DecisionLogKind::LATE_WAIVED->value,
                'old_value' => $minutes,
                'new_value' => 0,
                'reason' => $reason,
                'user_id' => $by->id,
                'created_at' => now(),
            ]);

            return $t->fresh();
        });
    }

    public function restore(Timecard $t, User $by): Timecard
    {
        $this->authorize($by, $t);
        $this->assertOpenPeriod($t);

        return DB::transaction(function () use ($t, $by) {
            $t->forceFill(['late_waived' => false])->save();

            $minutes = (int) $t->late_in_minutes + (int) $t->early_out_minutes;

            TimecardDecisionLog::query()->create([
                'timecard_id' => $t->id,
                'employment_id' => $t->employment_id,
                'work_date' => Carbon::parse($t->work_date)->toDateString(),
                'kind' => DecisionLogKind::LATE_RESTORED->value,
                'old_value' => 0,
                'new_value' => $minutes,
                'reason' => null,
                'user_id' => $by->id,
                'created_at' => now(),
            ]);

            return $t->fresh();
        });
    }

    /** late_in_minutes + early_out_minutes, or 0 when waived. */
    public static function chargeable(Timecard $t): int
    {
        if ($t->late_waived) {
            return 0;
        }

        return (int) $t->late_in_minutes + (int) $t->early_out_minutes;
    }

    private function authorize(User $by, Timecard $t): void
    {
        if (! $by->can('late.waive')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if (! $this->canAccessCompany($by, (int) $t->company_id)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    private function assertOpenPeriod(Timecard $t): void
    {
        $workDate = Carbon::parse($t->work_date)->toDateString();
        if (app(PayPeriodService::class)->isDateLocked((int) $t->company_id, $workDate)) {
            throw new InvalidOtDecision('That period is closed.');
        }
    }

    private function canAccessCompany(User $by, int $companyId): bool
    {
        if ($by->all_companies) {
            return true;
        }

        return $by->companies()->where('companies.id', $companyId)->exists();
    }
}
