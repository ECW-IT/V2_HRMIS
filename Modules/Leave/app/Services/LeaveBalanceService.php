<?php

namespace Modules\Leave\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Enums\LedgerEntryKind;
use Modules\Leave\Exceptions\InvalidLeaveRequest;
use Modules\Leave\Models\LeaveLedgerEntry;
use Modules\Leave\Models\LeaveRequest;

final class LeaveBalanceService
{
    public function __construct(private readonly LeavePolicyService $policies) {}

    /** Writes the GRANT for the period if it is not there yet. Returns the entry or null when no policy applies. */
    public function ensureGrant(Employment $e, int $leaveTypeId, PayPeriod $p): ?LeaveLedgerEntry
    {
        $existing = LeaveLedgerEntry::query()
            ->where('employment_id', $e->id)
            ->where('leave_type_id', $leaveTypeId)
            ->where('pay_period_id', $p->id)
            ->where('kind', LedgerEntryKind::GRANT)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $periodStart = Carbon::parse($p->start_date)->toDateString();
        $hire = Carbon::parse($e->hire_date)->toDateString();
        $resolveOn = $hire > $periodStart ? $hire : $periodStart;

        $policy = $this->policies->resolve($e, $leaveTypeId, $resolveOn);
        if ($policy === null) {
            return null;
        }

        return LeaveLedgerEntry::query()->create([
            'employment_id' => $e->id,
            'leave_type_id' => $leaveTypeId,
            'pay_period_id' => $p->id,
            'kind' => LedgerEntryKind::GRANT->value,
            'days' => $policy->days_per_period,
            'leave_request_id' => null,
            'note' => null,
            'created_by' => null,
            'created_at' => now(),
        ]);
    }

    /** Sum of ledger days for the period (after ensureGrant). */
    public function balance(Employment $e, int $leaveTypeId, PayPeriod $p): float
    {
        $this->ensureGrant($e, $leaveTypeId, $p);

        return (float) LeaveLedgerEntry::query()
            ->where('employment_id', $e->id)
            ->where('leave_type_id', $leaveTypeId)
            ->where('pay_period_id', $p->id)
            ->sum('days');
    }

    /** balance() minus the days of PENDING requests in the period. */
    public function available(Employment $e, int $leaveTypeId, PayPeriod $p): float
    {
        $balance = $this->balance($e, $leaveTypeId, $p);

        $pending = (float) LeaveRequest::withoutGlobalScopes()
            ->where('employment_id', $e->id)
            ->where('leave_type_id', $leaveTypeId)
            ->where('status', LeaveStatus::PENDING)
            ->whereDate('date', '>=', Carbon::parse($p->start_date)->toDateString())
            ->whereDate('date', '<=', Carbon::parse($p->end_date)->toDateString())
            ->sum('days');

        return $balance - $pending;
    }

    public function adjust(Employment $e, int $leaveTypeId, PayPeriod $p, float $days, string $note, User $by): LeaveLedgerEntry
    {
        if (! $by->can('leave.manage')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if (app(PayPeriodService::class)->isDateLocked((int) $e->company_id, $p->start_date)) {
            throw new InvalidLeaveRequest('That period is closed.');
        }

        $this->ensureGrant($e, $leaveTypeId, $p);

        return LeaveLedgerEntry::query()->create([
            'employment_id' => $e->id,
            'leave_type_id' => $leaveTypeId,
            'pay_period_id' => $p->id,
            'kind' => LedgerEntryKind::ADJUSTMENT->value,
            'days' => $days,
            'leave_request_id' => null,
            'note' => $note,
            'created_by' => $by->id,
            'created_at' => now(),
        ]);
    }
}
