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
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Services\PayPeriodService;

final class OtApprovalService
{
    /** Computed OT of a timecard in minutes: ot_morning_minutes + ot_evening_minutes. */
    public function computed(Timecard $t): int
    {
        return (int) $t->ot_morning_minutes + (int) $t->ot_evening_minutes;
    }

    /** Approve $minutes (null = all computed). */
    public function approve(Timecard $t, ?int $minutes, User $by, ?string $reason = null): Timecard
    {
        $this->authorize($by, $t);
        $this->assertCanDecide($t, $by);

        $computed = $this->computed($t);
        $approved = $minutes ?? $computed;

        if ($approved < 0 || $approved > $computed) {
            throw new InvalidOtDecision('Approved minutes must be between 0 and computed OT.');
        }

        $block = (int) config('attendance.ot_block_minutes', 30);
        if ($block > 0 && $approved % $block !== 0) {
            throw new InvalidOtDecision('Approved minutes must be a multiple of the OT block.');
        }

        if ($approved < $computed && ($reason === null || trim($reason) === '')) {
            throw new InvalidOtDecision('A reason is required when approving less than computed OT.');
        }

        return $this->writeDecision($t, $approved, $by, $reason, DecisionLogKind::OT_APPROVED);
    }

    /** Approve all computed OT on each timecard. Returns ['approved' => n, 'skipped' => [timecard_id => reason]]. */
    public function approveMany(array $timecardIds, User $by): array
    {
        $approved = 0;
        $skipped = [];

        foreach ($timecardIds as $id) {
            $timecard = Timecard::withoutGlobalScopes()->find($id);
            if ($timecard === null) {
                $skipped[(int) $id] = 'Timecard not found.';

                continue;
            }

            try {
                $this->approve($timecard, null, $by);
                $approved++;
            } catch (\Throwable $e) {
                $skipped[(int) $id] = $e->getMessage();
            }
        }

        return ['approved' => $approved, 'skipped' => $skipped];
    }

    /** Set approved OT to 0 (refused). Reason required. */
    public function refuse(Timecard $t, User $by, string $reason): Timecard
    {
        if (trim($reason) === '') {
            throw new InvalidOtDecision('A reason is required to refuse OT.');
        }

        return $this->approve($t, 0, $by, $reason);
    }

    /** Back to undecided (null). */
    public function reset(Timecard $t, User $by): Timecard
    {
        $this->authorize($by, $t);
        $this->assertOpenPeriod($t);
        $this->assertNotOwn($t, $by);

        $old = $t->ot_approved_minutes;

        return DB::transaction(function () use ($t, $by, $old) {
            $t->forceFill([
                'ot_approved_minutes' => null,
                'ot_decided_by' => null,
                'ot_decided_at' => null,
            ])->save();

            $this->log($t, DecisionLogKind::OT_CLEARED, $old, null, null, $by->id);

            return $t->fresh();
        });
    }

    /** Timecards in the period with computed OT > 0 and ot_approved_minutes null. */
    public function undecidedCount(PayPeriod $period): int
    {
        return Timecard::withoutGlobalScopes()
            ->where('pay_period_id', $period->id)
            ->whereNull('ot_approved_minutes')
            ->whereRaw('(ot_morning_minutes + ot_evening_minutes) > 0')
            ->count();
    }

    private function writeDecision(
        Timecard $t,
        int $approved,
        User $by,
        ?string $reason,
        DecisionLogKind $kind,
    ): Timecard {
        $old = $t->ot_approved_minutes;

        return DB::transaction(function () use ($t, $approved, $by, $reason, $kind, $old) {
            $t->forceFill([
                'ot_approved_minutes' => $approved,
                'ot_decided_by' => $by->id,
                'ot_decided_at' => now(),
            ])->save();

            $this->log($t, $kind, $old, $approved, $reason, $by->id);

            return $t->fresh();
        });
    }

    private function log(
        Timecard $t,
        DecisionLogKind $kind,
        ?int $old,
        ?int $new,
        ?string $reason,
        ?int $userId,
    ): void {
        TimecardDecisionLog::query()->create([
            'timecard_id' => $t->id,
            'employment_id' => $t->employment_id,
            'work_date' => Carbon::parse($t->work_date)->toDateString(),
            'kind' => $kind->value,
            'old_value' => $old,
            'new_value' => $new,
            'reason' => $reason,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }

    private function authorize(User $by, Timecard $t): void
    {
        if (! $by->can('ot.approve')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if (! $this->canAccessCompany($by, (int) $t->company_id)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    private function assertCanDecide(Timecard $t, User $by): void
    {
        $this->assertOpenPeriod($t);
        $this->assertNotOwn($t, $by);

        if ($t->exception !== null) {
            throw new InvalidOtDecision('A day with an exception cannot have OT approved.');
        }

        if ($this->computed($t) <= 0) {
            throw new InvalidOtDecision('There is no computed OT to approve.');
        }
    }

    private function assertOpenPeriod(Timecard $t): void
    {
        $workDate = Carbon::parse($t->work_date)->toDateString();
        if (app(PayPeriodService::class)->isDateLocked((int) $t->company_id, $workDate)) {
            throw new InvalidOtDecision('That period is closed.');
        }
    }

    private function assertNotOwn(Timecard $t, User $by): void
    {
        $employment = $t->relationLoaded('employment')
            ? $t->employment
            : $t->employment()->first();

        if ($employment !== null && $by->person_id !== null && (int) $by->person_id === (int) $employment->person_id) {
            throw new InvalidOtDecision('You cannot decide OT on your own timecard.');
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
