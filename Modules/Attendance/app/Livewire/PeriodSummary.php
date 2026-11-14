<?php

namespace Modules\Attendance\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Attendance\Enums\OverrideField;
use Modules\Attendance\Exports\PeriodSummaryColumns;
use Modules\Attendance\Exports\PeriodSummaryExport;
use Modules\Attendance\Models\PeriodTimeSummary;
use Modules\Attendance\Models\SummaryOverride;
use Modules\Attendance\Services\SummaryService;
use Modules\Attendance\Summaries\SummaryCalculator;
use Modules\Attendance\Summaries\SummaryFigures;
use Modules\Attendance\Support\AttendanceNav;
use Modules\Calendar\Enums\PeriodStatus;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Grade;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PeriodSummary extends Component
{
    public const OPEN_BANNER = 'Not closed — figures can still change.';

    public ?int $periodId = null;

    public ?int $version = null;

    public bool $showOverride = false;

    public ?int $overrideEmploymentId = null;

    public string $overrideField = 'NOPAY_DAYS';

    public string $overrideValue = '';

    public string $overrideReason = '';

    public string $error = '';

    public function mount(): void
    {
        $this->authorize('timecard.view');
        $open = PayPeriod::query()
            ->where('status', PeriodStatus::OPEN)
            ->orderBy('year')
            ->orderBy('month')
            ->value('id');
        $this->periodId = $open ?? PayPeriod::query()->orderByDesc('year')->orderByDesc('month')->value('id');
        $this->syncVersion();
    }

    public function updatedPeriodId(): void
    {
        $this->version = null;
        $this->syncVersion();
    }

    public function openOverride(int $employmentId): void
    {
        $this->authorize('period.override');
        $this->showOverride = true;
        $this->overrideEmploymentId = $employmentId;
        $this->overrideField = OverrideField::NOPAY_DAYS->value;
        $this->overrideValue = '';
        $this->overrideReason = '';
        $this->error = '';
    }

    public function saveOverride(): void
    {
        $this->authorize('period.override');

        try {
            app(SummaryService::class)->setOverride(
                Employment::query()->findOrFail($this->overrideEmploymentId),
                $this->period(),
                OverrideField::from($this->overrideField),
                (float) $this->overrideValue,
                $this->overrideReason,
                $this->user(),
            );
            $this->showOverride = false;
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('timecard.view');
        $table = $this->table($this->period());

        return Excel::download(new PeriodSummaryExport($table['export']), 'period-summary.xlsx');
    }

    public function render(): View
    {
        $this->authorize('timecard.view');
        $period = $this->periodId ? PayPeriod::query()->find($this->periodId) : null;
        $table = $period === null
            ? ['rows' => [], 'export' => [], 'versions' => collect()]
            : $this->table($period);

        return view('attendance::livewire.period-summary', [
            'periods' => PayPeriod::query()->orderByDesc('year')->orderByDesc('month')->get(),
            'period' => $period,
            'headings' => PeriodSummaryColumns::headings(),
            'rows' => $table['rows'],
            'versions' => $table['versions'],
            'banner' => $period !== null && $period->status === PeriodStatus::OPEN ? self::OPEN_BANNER : null,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Time & Leave',
            'title' => 'Period summary',
            'moduleNav' => AttendanceNav::items('period-summary'),
        ]);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, export: list<list<string>>, versions: Collection<int, int>}
     */
    private function table(PayPeriod $period): array
    {
        $versions = PeriodTimeSummary::query()
            ->where('pay_period_id', $period->id)
            ->distinct()
            ->orderBy('version')
            ->pluck('version')
            ->map(fn ($version) => (int) $version);

        $closed = $period->status === PeriodStatus::CLOSED;
        $stored = collect();
        if ($closed) {
            $version = $this->version ?? ($versions->max() ?: null);
            if ($version !== null) {
                $stored = PeriodTimeSummary::query()
                    ->where('pay_period_id', $period->id)
                    ->where('version', $version)
                    ->orderBy('employment_id')
                    ->get()
                    ->keyBy('employment_id');
            }
        }

        $employments = $closed
            ? Employment::query()->with('person')->whereIn('id', $stored->keys())->orderBy('id')->get()
            : app(SummaryService::class)->employmentsIn($period)->load('person');

        $calculator = app(SummaryCalculator::class);
        $summaries = app(SummaryService::class);
        $rows = [];
        $totals = array_fill(0, count(PeriodSummaryColumns::headings()), 0.0);
        $totalLabels = [4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19];

        foreach ($employments as $employment) {
            if ($closed) {
                $record = $stored->get($employment->id);
                $values = $this->fromStored($record);
            } else {
                $values = $this->fromFigures($summaries->current($employment, $period));
            }

            $raw = $calculator->for($employment, $period, false);
            $overrides = SummaryOverride::query()
                ->where('pay_period_id', $period->id)
                ->where('employment_id', $employment->id)
                ->whereNull('cancelled_at')
                ->get();

            $grade = Grade::query()->find($values['grade_id']);
            $cells = [
                (string) $employment->employee_no,
                (string) $employment->person?->name_with_initials,
                (string) ($grade?->name ?? ''),
                (string) $values['mode'],
                (string) $values['employed'],
                $this->days($values['expected']),
                $this->days($values['worked']),
                (string) $values['full'],
                (string) $values['half'],
                (string) $values['short'],
                (string) $values['short_leave'],
                $this->days($values['paid']),
                $this->days($values['unpaid']),
                $this->days($values['credited']),
                $this->days($values['extra']),
                $this->days($values['nopay']),
                (string) $values['late_total'],
                (string) $values['late_chargeable'],
                $this->hours($values['ot_normal']),
                $this->hours($values['ot_double']),
            ];

            foreach ($totalLabels as $index) {
                $totals[$index] += (float) $cells[$index];
            }

            $nopayOverride = $overrides->first(fn (SummaryOverride $row) => $row->field === OverrideField::NOPAY_DAYS);
            $lateOverride = $overrides->first(fn (SummaryOverride $row) => $row->field === OverrideField::LATE_MINUTES_CHARGEABLE);

            $rows[] = [
                'employment_id' => $employment->id,
                'cells' => $cells,
                'nopay_highlight' => $nopayOverride !== null,
                'late_highlight' => $lateOverride !== null,
                'nopay_title' => $nopayOverride === null ? null : 'Was '.$this->days($raw->nopayDays).'. '.$nopayOverride->reason,
                'late_title' => $lateOverride === null ? null : 'Was '.$raw->lateMinutesChargeable.'. '.$lateOverride->reason,
            ];
        }

        $totalRow = array_map(fn ($index) => in_array($index, $totalLabels, true)
            ? (in_array($index, [7, 8, 9, 10, 16, 17], true) ? (string) (int) round($totals[$index]) : $this->days($totals[$index]))
            : '', array_keys($totals));
        $totalRow[0] = 'Total';

        $export = array_map(fn (array $row) => $row['cells'], $rows);
        $export[] = $totalRow;
        $rows[] = [
            'employment_id' => null,
            'cells' => $totalRow,
            'nopay_highlight' => false,
            'late_highlight' => false,
            'nopay_title' => null,
            'late_title' => null,
        ];

        return ['rows' => $rows, 'export' => $export, 'versions' => $versions];
    }

    /** @return array<string, mixed> */
    private function fromFigures(SummaryFigures $figures): array
    {
        return [
            'grade_id' => $figures->gradeId,
            'mode' => $figures->attendanceMode->value,
            'employed' => $figures->employedDays,
            'expected' => $figures->expectedDays,
            'worked' => $figures->workedDays,
            'full' => $figures->fullDays,
            'half' => $figures->halfDays,
            'short' => $figures->shortDays,
            'short_leave' => $figures->shortLeaveCount,
            'paid' => $figures->paidLeaveDays,
            'unpaid' => $figures->unpaidLeaveDays,
            'credited' => $figures->creditedDays,
            'extra' => $figures->extraDays,
            'nopay' => $figures->nopayDays,
            'late_total' => $figures->lateMinutesTotal,
            'late_chargeable' => $figures->lateMinutesChargeable,
            'ot_normal' => $figures->otNormalMinutes,
            'ot_double' => $figures->otDoubleMinutes,
        ];
    }

    /** @return array<string, mixed> */
    private function fromStored(PeriodTimeSummary $row): array
    {
        return [
            'grade_id' => (int) $row->grade_id,
            'mode' => $row->attendance_mode->value,
            'employed' => (int) $row->employed_days,
            'expected' => (float) $row->expected_days,
            'worked' => (float) $row->worked_days,
            'full' => (int) $row->full_days,
            'half' => (int) $row->half_days,
            'short' => (int) $row->short_days,
            'short_leave' => (int) $row->short_leave_count,
            'paid' => (float) $row->paid_leave_days,
            'unpaid' => (float) $row->unpaid_leave_days,
            'credited' => (float) $row->credited_days,
            'extra' => (float) $row->extra_days,
            'nopay' => (float) $row->nopay_days,
            'late_total' => (int) $row->late_minutes_total,
            'late_chargeable' => (int) $row->late_minutes_chargeable,
            'ot_normal' => (int) $row->ot_normal_minutes,
            'ot_double' => (int) $row->ot_double_minutes,
        ];
    }

    private function days(float|int|string $value): string
    {
        return number_format((float) $value, 2);
    }

    private function hours(float|int|string $minutes): string
    {
        return number_format(((float) $minutes) / 60, 2);
    }

    private function syncVersion(): void
    {
        if ($this->periodId === null) {
            return;
        }

        $period = PayPeriod::query()->find($this->periodId);
        if ($period === null || $period->status !== PeriodStatus::CLOSED) {
            $this->version = null;

            return;
        }

        $max = PeriodTimeSummary::query()->where('pay_period_id', $period->id)->max('version');
        $this->version = $max === null ? null : (int) $max;
    }

    private function period(): PayPeriod
    {
        return PayPeriod::query()->findOrFail($this->periodId);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
