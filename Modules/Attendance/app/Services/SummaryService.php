<?php

namespace Modules\Attendance\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Enums\AttendanceMode;
use Modules\Attendance\Enums\OverrideField;
use Modules\Attendance\Exceptions\InvalidOverride;
use Modules\Attendance\Models\PeriodTimeSummary;
use Modules\Attendance\Models\SummaryOverride;
use Modules\Attendance\Summaries\SummaryCalculator;
use Modules\Attendance\Summaries\SummaryFigures;
use Modules\Calendar\Enums\PeriodStatus;
use Modules\Calendar\Exceptions\InvalidPeriodAction;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;

final class SummaryService
{
    public function __construct(private readonly SummaryCalculator $calculator) {}

    /** Employments with at least one employed day in the period. */
    public function employmentsIn(PayPeriod $p): Collection
    {
        $start = Carbon::parse($p->start_date)->toDateString();
        $end = Carbon::parse($p->end_date)->toDateString();

        return Employment::query()
            ->where('company_id', $p->company_id)
            ->whereDate('hire_date', '<=', $end)
            ->where(function ($query) use ($start) {
                $query->whereNull('exit_date')->orWhereDate('exit_date', '>=', $start);
            })
            ->orderBy('id')
            ->get();
    }

    /** Live figures (open period) or the latest stored version (closed period). */
    public function current(Employment $e, PayPeriod $p): SummaryFigures
    {
        if ($p->status === PeriodStatus::CLOSED) {
            $row = $this->latestFor($e, $p);
            if ($row === null) {
                throw new InvalidPeriodAction('No stored summary for this employee.');
            }

            return $this->fromRow($row);
        }

        return $this->calculator->for($e, $p);
    }

    /** Stored rows of the latest version. Empty when the period was never closed. */
    public function final(PayPeriod $p): Collection
    {
        $version = PeriodTimeSummary::query()
            ->where('pay_period_id', $p->id)
            ->max('version');

        if ($version === null) {
            return collect();
        }

        return PeriodTimeSummary::query()
            ->where('pay_period_id', $p->id)
            ->where('version', $version)
            ->orderBy('employment_id')
            ->get();
    }

    public function setOverride(Employment $e, PayPeriod $p, OverrideField $field, float $value, string $reason, User $by): SummaryOverride
    {
        $this->authorize($by, (int) $e->company_id);

        if ($p->status !== PeriodStatus::OPEN) {
            throw new InvalidOverride('The period is closed.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new InvalidOverride('A reason of at least 10 characters is required.');
        }

        if ($value < 0) {
            throw new InvalidOverride('The value cannot be negative.');
        }

        if ($field === OverrideField::NOPAY_DAYS) {
            $daysInMonth = (int) Carbon::create((int) $p->year, (int) $p->month, 1)->daysInMonth;
            if ($value > $daysInMonth || ! $this->quarterStep($value)) {
                throw new InvalidOverride('No-pay days must be in steps of 0.25 and not more than the days in the month.');
            }
        }

        if ($field === OverrideField::LATE_MINUTES_CHARGEABLE && ! $this->wholeNumber($value)) {
            throw new InvalidOverride('Chargeable late minutes must be a whole number.');
        }

        return DB::transaction(function () use ($e, $p, $field, $value, $reason, $by) {
            SummaryOverride::query()
                ->where('pay_period_id', $p->id)
                ->where('employment_id', $e->id)
                ->where('field', $field)
                ->whereNull('cancelled_at')
                ->lockForUpdate()
                ->get()
                ->each(function (SummaryOverride $row): void {
                    $row->forceFill(['cancelled_at' => now()])->save();
                });

            return SummaryOverride::query()->create([
                'pay_period_id' => $p->id,
                'employment_id' => $e->id,
                'field' => $field,
                'value' => $value,
                'reason' => $reason,
                'set_by' => $by->id,
                'cancelled_at' => null,
            ]);
        });
    }

    public function cancelOverride(SummaryOverride $o, User $by): void
    {
        $employment = Employment::query()->findOrFail($o->employment_id);
        $this->authorize($by, (int) $employment->company_id);

        $period = PayPeriod::query()->findOrFail($o->pay_period_id);
        if ($period->status !== PeriodStatus::OPEN) {
            throw new InvalidOverride('The period is closed.');
        }

        $o->forceFill(['cancelled_at' => now()])->save();
    }

    private function authorize(User $by, int $companyId): void
    {
        if (! $by->can('period.override') || ! $this->canAccessCompany($by, $companyId)) {
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

    private function quarterStep(float $value): bool
    {
        $quarters = $value * 4;

        return abs($quarters - round($quarters)) < 0.00001;
    }

    private function wholeNumber(float $value): bool
    {
        return abs($value - round($value)) < 0.00001;
    }

    private function latestFor(Employment $e, PayPeriod $p): ?PeriodTimeSummary
    {
        return PeriodTimeSummary::query()
            ->where('pay_period_id', $p->id)
            ->where('employment_id', $e->id)
            ->orderByDesc('version')
            ->first();
    }

    private function fromRow(PeriodTimeSummary $row): SummaryFigures
    {
        $mode = $row->attendance_mode instanceof AttendanceMode
            ? $row->attendance_mode
            : AttendanceMode::from((string) $row->attendance_mode);

        return new SummaryFigures(
            gradeId: (int) $row->grade_id,
            attendanceMode: $mode,
            employedDays: (int) $row->employed_days,
            expectedDays: (float) $row->expected_days,
            workedDays: (float) $row->worked_days,
            fullDays: (int) $row->full_days,
            halfDays: (int) $row->half_days,
            shortDays: (int) $row->short_days,
            shortLeaveCount: (int) $row->short_leave_count,
            paidLeaveDays: (float) $row->paid_leave_days,
            unpaidLeaveDays: (float) $row->unpaid_leave_days,
            creditedDays: (float) $row->credited_days,
            extraDays: (float) $row->extra_days,
            nopayDays: (float) $row->nopay_days,
            lateMinutesTotal: (int) $row->late_minutes_total,
            lateMinutesChargeable: (int) $row->late_minutes_chargeable,
            otNormalMinutes: (int) $row->ot_normal_minutes,
            otDoubleMinutes: (int) $row->ot_double_minutes,
            hasOverride: (bool) $row->has_override,
        );
    }
}
