<?php

namespace Modules\Attendance\Services;

use App\Models\User;
use App\Support\Audit\AuditLogger;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Enums\AdjustmentStatus;
use Modules\Attendance\Enums\AdjustmentType;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Exceptions\InvalidAdjustment;
use Modules\Attendance\Models\AttendanceAdjustment;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Timecards\WorkDate;
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\Workflow\Enums\ApprovalActionType;
use Modules\Workflow\Enums\ApprovalStatus;
use Modules\Workflow\Services\ApprovalService;

final class AdjustmentService
{
    public function __construct(private readonly ApprovalService $approvals) {}

    public function addPunch(Employment $e, \DateTimeInterface|string $punchedAt, string $reason, User $by): AttendanceAdjustment
    {
        $this->authorize($by, $e);
        $this->assertReason($reason);

        $at = CarbonImmutable::parse($punchedAt, config('app.timezone'));
        $workDate = WorkDate::of($at);
        $this->assertWorkDateAllowed($e, $workDate, $at);
        $this->assertNoDuplicateAdd((int) $e->id, $at);

        return $this->createAdjustment(
            $e,
            $workDate,
            AdjustmentType::ADD_PUNCH,
            $at->format('Y-m-d H:i:s'),
            null,
            $reason,
            $by,
        );
    }

    public function voidPunch(Punch $punch, string $reason, User $by): AttendanceAdjustment
    {
        if ($punch->resolution !== PunchResolution::RESOLVED || $punch->employment_id === null) {
            throw new InvalidAdjustment('Only a resolved punch can be voided.');
        }

        $employment = Employment::query()->findOrFail($punch->employment_id);
        $this->authorize($by, $employment);
        $this->assertReason($reason);

        $at = CarbonImmutable::parse($punch->punched_at, config('app.timezone'));
        $workDate = WorkDate::of($at);
        $this->assertWorkDateAllowed($employment, $workDate, $at);

        $exists = AttendanceAdjustment::withoutGlobalScopes()
            ->where('punch_id', $punch->id)
            ->where('type', AdjustmentType::VOID_PUNCH)
            ->whereIn('status', [AdjustmentStatus::PENDING, AdjustmentStatus::APPROVED])
            ->exists();

        if ($exists) {
            throw new InvalidAdjustment('This punch already has a void adjustment.');
        }

        return $this->createAdjustment(
            $employment,
            $workDate,
            AdjustmentType::VOID_PUNCH,
            null,
            (int) $punch->id,
            $reason,
            $by,
        );
    }

    public function cancel(AttendanceAdjustment $a, User $by): AttendanceAdjustment
    {
        $employment = Employment::query()->findOrFail($a->employment_id);

        if ($a->status === AdjustmentStatus::PENDING) {
            if ((int) $a->requested_by !== (int) $by->id) {
                throw new AuthorizationException('This action is unauthorized.');
            }

            return DB::transaction(function () use ($a, $by) {
                if ($a->approval_request_id !== null) {
                    $request = $a->approvalRequest;
                    if ($request !== null && $request->status === ApprovalStatus::PENDING) {
                        $this->approvals->cancel($request, $by);
                    }
                }

                $a->update(['status' => AdjustmentStatus::CANCELLED]);

                return $a->fresh();
            });
        }

        if ($a->status === AdjustmentStatus::APPROVED) {
            $this->authorizeDirect($by, $employment);

            $workDate = Carbon::parse($a->work_date)->toDateString();
            if (app(PayPeriodService::class)->isDateLocked((int) $a->company_id, $workDate)) {
                throw new InvalidAdjustment('That period is closed.');
            }

            $a->update([
                'status' => AdjustmentStatus::CANCELLED,
                'decided_by' => $by->id,
                'decided_at' => now(),
            ]);

            app(TimecardService::class)->compute((int) $a->employment_id, $workDate);

            return $a->fresh();
        }

        throw new InvalidAdjustment('This adjustment cannot be cancelled.');
    }

    /** Adjustments for the employment and work date with status APPROVED. */
    public function approvedFor(int $employmentId, string $workDate): Collection
    {
        return AttendanceAdjustment::withoutGlobalScopes()
            ->where('employment_id', $employmentId)
            ->whereDate('work_date', $workDate)
            ->where('status', AdjustmentStatus::APPROVED)
            ->orderBy('id')
            ->get();
    }

    /** Count of PENDING adjustments with work_date inside the period. */
    public function pendingCount(PayPeriod $period): int
    {
        return AttendanceAdjustment::withoutGlobalScopes()
            ->where('company_id', $period->company_id)
            ->where('status', AdjustmentStatus::PENDING)
            ->whereDate('work_date', '>=', Carbon::parse($period->start_date)->toDateString())
            ->whereDate('work_date', '<=', Carbon::parse($period->end_date)->toDateString())
            ->count();
    }

    public function markApproved(AttendanceAdjustment $a, User $by): AttendanceAdjustment
    {
        $a->update([
            'status' => AdjustmentStatus::APPROVED,
            'decided_by' => $by->id,
            'decided_at' => now(),
        ]);

        app(TimecardService::class)->compute(
            (int) $a->employment_id,
            Carbon::parse($a->work_date)->toDateString(),
        );

        return $a->fresh();
    }

    public function markRejected(AttendanceAdjustment $a): AttendanceAdjustment
    {
        $a->update(['status' => AdjustmentStatus::REJECTED]);

        return $a->fresh();
    }

    private function createAdjustment(
        Employment $e,
        string $workDate,
        AdjustmentType $type,
        ?string $punchedAt,
        ?int $punchId,
        string $reason,
        User $by,
    ): AttendanceAdjustment {
        $direct = $by->can('attendance.adjust.direct');

        return DB::transaction(function () use ($e, $workDate, $type, $punchedAt, $punchId, $reason, $by, $direct) {
            $adjustment = AuditLogger::withReason($reason, function () use ($e, $workDate, $type, $punchedAt, $punchId, $reason, $by, $direct) {
                return AttendanceAdjustment::query()->create([
                    'company_id' => $e->company_id,
                    'employment_id' => $e->id,
                    'work_date' => $workDate,
                    'type' => $type,
                    'punched_at' => $punchedAt,
                    'punch_id' => $punchId,
                    'reason' => $reason,
                    'status' => $direct ? AdjustmentStatus::APPROVED : AdjustmentStatus::PENDING,
                    'approval_request_id' => null,
                    'requested_by' => $by->id,
                    'decided_by' => $direct ? $by->id : null,
                    'decided_at' => $direct ? now() : null,
                ]);
            });

            if ($direct) {
                app(TimecardService::class)->compute((int) $e->id, $workDate);

                return $adjustment->fresh();
            }

            $time = $punchedAt;
            if ($time === null && $punchId !== null) {
                $voided = Punch::query()->find($punchId);
                $time = $voided !== null ? Carbon::parse($voided->punched_at)->format('Y-m-d H:i:s') : null;
            }

            $request = $this->approvals->submit(
                'ATTENDANCE_ADJUSTMENT',
                (int) $e->company_id,
                AttendanceAdjustment::class,
                $adjustment->id,
                ApprovalActionType::CREATE,
                [
                    'type' => $type->value,
                    'time' => $time,
                    'reason' => $reason,
                ],
                $by,
            );

            $adjustment->update(['approval_request_id' => $request->id]);

            return $adjustment->fresh(['approvalRequest']);
        });
    }

    private function authorize(User $by, Employment $e): void
    {
        if (! $by->can('attendance.adjust')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if (! $this->canAccessCompany($by, (int) $e->company_id)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    private function authorizeDirect(User $by, Employment $e): void
    {
        $this->authorize($by, $e);

        if (! $by->can('attendance.adjust.direct')) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    private function canAccessCompany(User $by, int $companyId): bool
    {
        if ($by->all_companies) {
            return true;
        }

        return $by->companies()->where('companies.id', $companyId)->exists();
    }

    private function assertReason(string $reason): void
    {
        if (mb_strlen(trim($reason)) < 5) {
            throw new InvalidAdjustment('A reason of at least 5 characters is required.');
        }
    }

    private function assertWorkDateAllowed(Employment $e, string $workDate, CarbonImmutable $punchedAt): void
    {
        if (app(PayPeriodService::class)->isDateLocked((int) $e->company_id, $workDate)) {
            throw new InvalidAdjustment('That period is closed.');
        }

        $hire = Carbon::parse($e->hire_date)->toDateString();
        $exit = $e->exit_date !== null ? Carbon::parse($e->exit_date)->toDateString() : null;
        $now = CarbonImmutable::now(config('app.timezone'));

        if ($workDate < $hire) {
            throw new InvalidAdjustment('The work date is before the hire date.');
        }

        if ($exit !== null && $workDate > $exit) {
            throw new InvalidAdjustment('The work date is after the exit date.');
        }

        if ($punchedAt->gt($now)) {
            throw new InvalidAdjustment('A punch time cannot be in the future.');
        }
    }

    private function assertNoDuplicateAdd(int $employmentId, CarbonImmutable $at): void
    {
        $minuteStart = $at->format('Y-m-d H:i:00');
        $minuteEnd = $at->format('Y-m-d H:i:59');

        $rawExists = Punch::query()
            ->where('employment_id', $employmentId)
            ->where('resolution', PunchResolution::RESOLVED)
            ->where('punched_at', '>=', $minuteStart)
            ->where('punched_at', '<=', $minuteEnd)
            ->exists();

        if ($rawExists) {
            throw new InvalidAdjustment('A punch already exists at that minute.');
        }

        $adjExists = AttendanceAdjustment::withoutGlobalScopes()
            ->where('employment_id', $employmentId)
            ->where('type', AdjustmentType::ADD_PUNCH)
            ->whereIn('status', [AdjustmentStatus::PENDING, AdjustmentStatus::APPROVED])
            ->where('punched_at', '>=', $minuteStart)
            ->where('punched_at', '<=', $minuteEnd)
            ->exists();

        if ($adjExists) {
            throw new InvalidAdjustment('An adjustment already exists at that minute.');
        }
    }
}
