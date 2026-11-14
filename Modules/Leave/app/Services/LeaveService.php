<?php

namespace Modules\Leave\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Exceptions\TimecardLocked;
use Modules\Attendance\Services\TimecardService;
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Models\Employment;
use Modules\Leave\Enums\LeavePortion;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Enums\LedgerEntryKind;
use Modules\Leave\Exceptions\InsufficientLeaveBalance;
use Modules\Leave\Exceptions\InvalidLeaveRequest;
use Modules\Leave\Models\LeaveLedgerEntry;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Modules\Workflow\Enums\ApprovalActionType;
use Modules\Workflow\Enums\ApprovalStatus;
use Modules\Workflow\Services\ApprovalService;

final class LeaveService
{
    public function __construct(
        private readonly LeavePolicyService $policies,
        private readonly LeaveBalanceService $balances,
        private readonly ApprovalService $approvals,
        private readonly PayPeriodService $periods,
    ) {}

    /** $opts: reason?, covering_employment_id?, pre_approved? (bool, needs leave.manage) */
    public function request(Employment $e, int $leaveTypeId, \DateTimeInterface|string $date, LeavePortion $portion, User $by, array $opts = []): LeaveRequest
    {
        $this->authorizeRequest($by, $e);

        $preApproved = (bool) ($opts['pre_approved'] ?? false);
        if ($preApproved && ! $by->can('leave.manage')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $day = Carbon::parse($date, config('app.timezone'))->toDateString();
        $type = LeaveType::query()->findOrFail($leaveTypeId);
        $this->assertRequestAllowed($e, $type, $day, $portion, $opts);

        $days = $portion === LeavePortion::FULL ? 1.0 : 0.5;
        $period = $this->periodFor($e, $day);

        if ($type->enforce_balance && $days > $this->balances->available($e, $leaveTypeId, $period)) {
            $available = $this->balances->available($e, $leaveTypeId, $period);
            throw new InsufficientLeaveBalance("Insufficient leave balance. Available: {$available}.");
        }

        // TODO Phase 4: requires_document check

        return DB::transaction(function () use ($e, $leaveTypeId, $day, $portion, $days, $by, $opts, $preApproved, $period) {
            if ($preApproved) {
                $request = LeaveRequest::query()->create([
                    'company_id' => $e->company_id,
                    'employment_id' => $e->id,
                    'leave_type_id' => $leaveTypeId,
                    'date' => $day,
                    'portion' => $portion,
                    'days' => $days,
                    'reason' => $opts['reason'] ?? null,
                    'covering_employment_id' => $opts['covering_employment_id'] ?? null,
                    'status' => LeaveStatus::APPROVED,
                    'approval_request_id' => null,
                    'requested_by' => $by->id,
                    'decided_at' => now(),
                ]);

                $this->applyApproved($request, $period);

                return $request->fresh();
            }

            $request = LeaveRequest::query()->create([
                'company_id' => $e->company_id,
                'employment_id' => $e->id,
                'leave_type_id' => $leaveTypeId,
                'date' => $day,
                'portion' => $portion,
                'days' => $days,
                'reason' => $opts['reason'] ?? null,
                'covering_employment_id' => $opts['covering_employment_id'] ?? null,
                'status' => LeaveStatus::PENDING,
                'approval_request_id' => null,
                'requested_by' => $by->id,
                'decided_at' => null,
            ]);

            $approval = $this->approvals->submit(
                'LEAVE_REQUEST',
                (int) $e->company_id,
                LeaveRequest::class,
                $request->id,
                ApprovalActionType::CREATE,
                [
                    'leave_type_id' => $leaveTypeId,
                    'date' => $day,
                    'portion' => $portion->value,
                    'days' => $days,
                    'reason' => $opts['reason'] ?? null,
                ],
                $by,
                (int) $e->id,
            );

            $request->update(['approval_request_id' => $approval->id]);

            return $request->fresh(['approvalRequest.definition']);
        });
    }

    /** One request per date, all or nothing. $skipDates: dates to leave out. @return Collection<LeaveRequest> */
    public function requestRange(Employment $e, int $leaveTypeId, $from, $to, User $by, array $opts = [], array $skipDates = []): Collection
    {
        $fromDate = Carbon::parse($from, config('app.timezone'))->startOfDay();
        $toDate = Carbon::parse($to, config('app.timezone'))->startOfDay();
        $skip = collect($skipDates)->map(fn ($d) => Carbon::parse($d)->toDateString())->all();

        $dates = [];
        $cursor = $fromDate->copy();
        while ($cursor->lte($toDate)) {
            $d = $cursor->toDateString();
            if (! in_array($d, $skip, true)) {
                $dates[] = $d;
            }
            $cursor->addDay();
        }

        return DB::transaction(function () use ($e, $leaveTypeId, $dates, $by, $opts) {
            $made = collect();
            foreach ($dates as $date) {
                $made->push($this->request($e, $leaveTypeId, $date, LeavePortion::FULL, $by, $opts));
            }

            return $made;
        });
    }

    public function cancel(LeaveRequest $r, User $by): LeaveRequest
    {
        if ($r->status === LeaveStatus::PENDING) {
            $isRequester = (int) $r->requested_by === (int) $by->id;
            if (! $isRequester && ! $by->can('leave.manage')) {
                throw new AuthorizationException('This action is unauthorized.');
            }

            return DB::transaction(function () use ($r, $by) {
                if ($r->approval_request_id !== null) {
                    $approval = $r->approvalRequest;
                    if ($approval !== null && $approval->status === ApprovalStatus::PENDING) {
                        if ((int) $approval->requested_by === (int) $by->id) {
                            $this->approvals->cancel($approval, $by);
                        } else {
                            $approval->update([
                                'status' => ApprovalStatus::CANCELLED,
                                'decided_at' => now(),
                            ]);
                        }
                    }
                }

                $r->update(['status' => LeaveStatus::CANCELLED]);

                return $r->fresh();
            });
        }

        if ($r->status === LeaveStatus::APPROVED) {
            if (! $by->can('leave.manage')) {
                throw new AuthorizationException('This action is unauthorized.');
            }

            $day = Carbon::parse($r->date)->toDateString();
            if ($this->periods->isDateLocked((int) $r->company_id, $day)) {
                throw new InvalidLeaveRequest('That period is closed.');
            }

            return DB::transaction(function () use ($r, $by, $day) {
                $period = $this->periodFor(Employment::query()->findOrFail($r->employment_id), $day);

                LeaveLedgerEntry::query()->create([
                    'employment_id' => $r->employment_id,
                    'leave_type_id' => $r->leave_type_id,
                    'pay_period_id' => $period->id,
                    'kind' => LedgerEntryKind::RESTORED->value,
                    'days' => (float) $r->days,
                    'leave_request_id' => $r->id,
                    'note' => null,
                    'created_by' => $by->id,
                    'created_at' => now(),
                ]);

                $r->update(['status' => LeaveStatus::CANCELLED]);
                $this->recomputeTimecard((int) $r->employment_id, $day);

                return $r->fresh();
            });
        }

        throw new InvalidLeaveRequest('This leave request cannot be cancelled.');
    }

    /** Approved leave days of the employment in the period, grouped: [leave_type_id => days]. */
    public function approvedDays(int $employmentId, PayPeriod $p): array
    {
        $rows = LeaveRequest::withoutGlobalScopes()
            ->where('employment_id', $employmentId)
            ->where('status', LeaveStatus::APPROVED)
            ->whereDate('date', '>=', Carbon::parse($p->start_date)->toDateString())
            ->whereDate('date', '<=', Carbon::parse($p->end_date)->toDateString())
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $id = (int) $row->leave_type_id;
            $grouped[$id] = ($grouped[$id] ?? 0) + (float) $row->days;
        }

        return $grouped;
    }

    public function pendingCount(PayPeriod $p): int
    {
        return LeaveRequest::withoutGlobalScopes()
            ->where('company_id', $p->company_id)
            ->where('status', LeaveStatus::PENDING)
            ->whereDate('date', '>=', Carbon::parse($p->start_date)->toDateString())
            ->whereDate('date', '<=', Carbon::parse($p->end_date)->toDateString())
            ->count();
    }

    public function markApproved(LeaveRequest $request): LeaveRequest
    {
        $day = Carbon::parse($request->date)->toDateString();
        $employment = Employment::query()->findOrFail($request->employment_id);
        $period = $this->periodFor($employment, $day);
        $type = LeaveType::query()->findOrFail($request->leave_type_id);

        if ($this->periods->isDateLocked((int) $request->company_id, $day)) {
            throw new InvalidLeaveRequest('That period is closed.');
        }

        if ($type->enforce_balance) {
            // On approval, check ledger balance (not available): other PENDING holds must not block this grant.
            $balance = $this->balances->balance($employment, (int) $request->leave_type_id, $period);
            if ((float) $request->days > $balance) {
                throw new InvalidLeaveRequest("Insufficient leave balance. Available: {$balance}.");
            }
        }

        return DB::transaction(function () use ($request, $period) {
            $request->update([
                'status' => LeaveStatus::APPROVED,
                'decided_at' => now(),
            ]);

            $this->applyApproved($request->fresh(), $period);

            return $request->fresh();
        });
    }

    public function markRejected(LeaveRequest $request): LeaveRequest
    {
        $request->update([
            'status' => LeaveStatus::REJECTED,
            'decided_at' => now(),
        ]);

        return $request->fresh();
    }

    private function applyApproved(LeaveRequest $request, PayPeriod $period): void
    {
        LeaveLedgerEntry::query()->create([
            'employment_id' => $request->employment_id,
            'leave_type_id' => $request->leave_type_id,
            'pay_period_id' => $period->id,
            'kind' => LedgerEntryKind::TAKEN->value,
            'days' => -1 * (float) $request->days,
            'leave_request_id' => $request->id,
            'note' => null,
            'created_by' => null,
            'created_at' => now(),
        ]);

        $this->recomputeTimecard((int) $request->employment_id, Carbon::parse($request->date)->toDateString());
    }

    private function recomputeTimecard(int $employmentId, string $day): void
    {
        try {
            app(TimecardService::class)->compute($employmentId, $day);
        } catch (TimecardLocked) {
            // unlocked only
        }
    }

    private function authorizeRequest(User $by, Employment $e): void
    {
        if (! $by->can('leave.request') && ! $by->can('leave.manage')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if (! $this->canAccessCompany($by, (int) $e->company_id)) {
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

    private function assertRequestAllowed(Employment $e, LeaveType $type, string $day, LeavePortion $portion, array $opts): void
    {
        if (! $type->is_active) {
            throw new InvalidLeaveRequest('This leave type is not active.');
        }

        if ($portion !== LeavePortion::FULL && ! $type->allows_half_day) {
            throw new InvalidLeaveRequest('This leave type does not allow half days.');
        }

        $hire = Carbon::parse($e->hire_date)->toDateString();
        $exit = $e->exit_date !== null ? Carbon::parse($e->exit_date)->toDateString() : null;
        $today = Carbon::now(config('app.timezone'))->toDateString();
        $maxAhead = Carbon::now(config('app.timezone'))->addDays(90)->toDateString();

        if ($day < $hire) {
            throw new InvalidLeaveRequest('The date is before the hire date.');
        }
        if ($exit !== null && $day > $exit) {
            throw new InvalidLeaveRequest('The date is after the exit date.');
        }
        if ($day > $maxAhead) {
            throw new InvalidLeaveRequest('The date is more than 90 days ahead.');
        }

        if ($this->periods->isDateLocked((int) $e->company_id, $day)) {
            throw new InvalidLeaveRequest('That period is closed.');
        }

        if ($this->policies->resolve($e, (int) $type->id, $day) === null) {
            throw new InvalidLeaveRequest("This employee has no entitlement to {$type->name}.");
        }

        $this->assertNoOverlap($e, $day, $portion);

        if (array_key_exists('covering_employment_id', $opts) && $opts['covering_employment_id'] !== null) {
            $coverId = (int) $opts['covering_employment_id'];
            if ($coverId === (int) $e->id) {
                throw new InvalidLeaveRequest('The covering person cannot be the employee themself.');
            }
            $cover = Employment::query()->find($coverId);
            if ($cover === null || $cover->status !== EmploymentStatus::ACTIVE) {
                throw new InvalidLeaveRequest('The covering person is not active.');
            }
            if ((int) $cover->company_id !== (int) $e->company_id) {
                throw new InvalidLeaveRequest('The covering person must be in the same company.');
            }
        }
    }

    private function assertNoOverlap(Employment $e, string $day, LeavePortion $portion): void
    {
        $existing = LeaveRequest::withoutGlobalScopes()
            ->where('employment_id', $e->id)
            ->whereDate('date', $day)
            ->whereIn('status', [LeaveStatus::PENDING, LeaveStatus::APPROVED])
            ->get();

        foreach ($existing as $row) {
            if ($portion === LeavePortion::FULL || $row->portion === LeavePortion::FULL) {
                throw new InvalidLeaveRequest('This overlaps another leave request on that date.');
            }
            if ($portion === $row->portion) {
                throw new InvalidLeaveRequest('This overlaps another leave request on that date.');
            }
        }
    }

    private function periodFor(Employment $e, string $day): PayPeriod
    {
        $company = $e->company;
        $carbon = Carbon::parse($day, config('app.timezone'));

        return $this->periods->ensure($company, (int) $carbon->year, (int) $carbon->month);
    }
}
