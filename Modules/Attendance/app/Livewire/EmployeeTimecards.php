<?php

namespace Modules\Attendance\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Attendance\Enums\AdjustmentStatus;
use Modules\Attendance\Enums\AdjustmentType;
use Modules\Attendance\Enums\DayType;
use Modules\Attendance\Enums\TimecardException;
use Modules\Attendance\Exports\TimecardsExport;
use Modules\Attendance\Models\AttendanceAdjustment;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Models\Timecard;
use Modules\Attendance\Services\AdjustmentService;
use Modules\Attendance\Services\LateWaiverService;
use Modules\Attendance\Services\OtApprovalService;
use Modules\Attendance\Services\SummaryService;
use Modules\Attendance\Summaries\SummaryFigures;
use Modules\Attendance\Support\AttendanceNav;
use Modules\Attendance\Timecards\WorkDate;
use Modules\Calendar\Enums\PeriodStatus;
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeTimecards extends Component
{
    public int $employmentId = 0;

    public string $month = '';

    public bool $embedded = false;

    public ?string $detailDate = null;

    public bool $showAdd = false;

    public bool $showVoid = false;

    public bool $showWaive = false;

    public bool $showOt = false;

    public string $punchedAt = '';

    public string $reason = '';

    public string $otMinutes = '';

    public ?int $punchId = null;

    public ?int $actionTimecardId = null;

    public string $error = '';

    public function mount(Employment $employment, ?string $month = null, bool $embedded = false): void
    {
        $this->authorize('timecard.view');
        $this->employmentId = $employment->id;
        $this->month = $month ?: now()->format('Y-m');
        $this->embedded = $embedded;
    }

    public function showDetail(string $date): void
    {
        $this->detailDate = $this->detailDate === $date ? null : $date;
    }

    public function openAdd(string $date): void
    {
        $this->authorize('attendance.adjust');
        $this->resetDialogs();
        $this->showAdd = true;
        $this->punchedAt = $date.' 08:00';
    }

    public function saveAdd(): void
    {
        $this->authorize('attendance.adjust');

        try {
            app(AdjustmentService::class)->addPunch($this->employment(), $this->punchedAt, $this->reason, $this->user());
            $this->resetDialogs();
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function openVoid(int $punchId): void
    {
        $this->authorize('attendance.adjust');
        $this->resetDialogs();
        $this->showVoid = true;
        $this->punchId = $punchId;
    }

    public function saveVoid(): void
    {
        $this->authorize('attendance.adjust');

        try {
            $punch = Punch::query()->findOrFail($this->punchId);
            app(AdjustmentService::class)->voidPunch($punch, $this->reason, $this->user());
            $this->resetDialogs();
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function openWaive(int $timecardId): void
    {
        $this->authorize('late.waive');
        $this->resetDialogs();
        $this->showWaive = true;
        $this->actionTimecardId = $timecardId;
    }

    public function saveWaive(): void
    {
        $this->authorize('late.waive');

        try {
            $card = Timecard::query()->findOrFail($this->actionTimecardId);
            app(LateWaiverService::class)->waive($card, $this->user(), $this->reason);
            $this->resetDialogs();
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function openOt(int $timecardId): void
    {
        $this->authorize('ot.approve');
        $this->resetDialogs();
        $this->showOt = true;
        $this->actionTimecardId = $timecardId;
    }

    public function approveOt(): void
    {
        $this->authorize('ot.approve');

        try {
            $card = Timecard::query()->findOrFail($this->actionTimecardId);
            $minutes = $this->otMinutes === '' ? null : (int) $this->otMinutes;
            app(OtApprovalService::class)->approve($card, $minutes, $this->user(), $this->reason !== '' ? $this->reason : null);
            $this->resetDialogs();
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function refuseOt(): void
    {
        $this->authorize('ot.approve');

        try {
            $card = Timecard::query()->findOrFail($this->actionTimecardId);
            app(OtApprovalService::class)->refuse($card, $this->user(), $this->reason);
            $this->resetDialogs();
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function resetOt(): void
    {
        $this->authorize('ot.approve');

        try {
            $card = Timecard::query()->findOrFail($this->actionTimecardId);
            app(OtApprovalService::class)->reset($card, $this->user());
            $this->resetDialogs();
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('timecard.view');
        $employment = $this->employment();

        return Excel::download(
            new TimecardsExport($this->rows($employment)),
            'timecard-'.$employment->employee_no.'-'.$this->month.'.xlsx',
        );
    }

    public function render(): View
    {
        $this->authorize('timecard.view');
        $employment = $this->employment()->load('person');
        [$period, $locked, $figures, $summaryError] = $this->footer($employment);
        $detail = $this->detailDate === null ? ['punches' => collect(), 'adjustments' => collect()] : $this->detail($employment, $this->detailDate);

        $view = view('attendance::livewire.employee-timecards', [
            'employment' => $employment,
            'rows' => $this->rows($employment),
            'locked' => $locked,
            'summaryError' => $summaryError,
            'worked' => $figures instanceof SummaryFigures ? number_format($figures->workedDays, 2) : null,
            'expected' => $figures instanceof SummaryFigures ? number_format($figures->expectedDays, 2) : null,
            'nopay' => $figures instanceof SummaryFigures ? number_format($figures->nopayDays, 2) : null,
            'detailPunches' => $detail['punches'],
            'detailAdjustments' => $detail['adjustments'],
            'closedLabel' => $period !== null && $period->status === PeriodStatus::CLOSED
                ? 'Closed (version '.$period->close_version.')'
                : null,
        ]);

        if ($this->embedded) {
            return $view;
        }

        return $view->layout('components.layouts.module', [
            'moduleName' => 'Time & Leave',
            'title' => 'Timecard',
            'moduleNav' => AttendanceNav::items('timecards'),
        ]);
    }

    private function employment(): Employment
    {
        return Employment::query()->findOrFail($this->employmentId);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    private function resetDialogs(): void
    {
        $this->showAdd = false;
        $this->showVoid = false;
        $this->showWaive = false;
        $this->showOt = false;
        $this->reason = '';
        $this->error = '';
        $this->otMinutes = '';
        $this->punchId = null;
        $this->actionTimecardId = null;
    }

    /** @return array{0: ?PayPeriod, 1: bool, 2: ?SummaryFigures, 3: ?string} */
    private function footer(Employment $employment): array
    {
        $start = $this->monthStart();
        $period = app(PayPeriodService::class)->ensure($employment->company, (int) $start->year, (int) $start->month);
        $locked = $period->status === PeriodStatus::CLOSED;

        try {
            return [$period, $locked, app(SummaryService::class)->current($employment, $period), null];
        } catch (DomainException $e) {
            return [$period, $locked, null, $e->getMessage()];
        }
    }

    /** @return list<array<string, mixed>> */
    private function rows(Employment $employment): array
    {
        $start = $this->monthStart();
        $end = $start->copy()->endOfMonth();
        $cards = Timecard::query()
            ->where('employment_id', $employment->id)
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->get()
            ->keyBy(fn (Timecard $card) => $card->work_date->toDateString());

        $pending = AttendanceAdjustment::query()
            ->where('employment_id', $employment->id)
            ->where('status', AdjustmentStatus::PENDING)
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->get()
            ->mapWithKeys(fn (AttendanceAdjustment $row) => [$row->work_date->toDateString() => true]);

        $rows = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            /** @var Timecard|null $card */
            $card = $cards->get($date);
            $ot = $card === null ? 0 : (int) $card->ot_morning_minutes + (int) $card->ot_evening_minutes;
            $late = $card === null ? 0 : (int) $card->late_in_minutes + (int) $card->early_out_minutes;
            $rows[] = [
                'date' => $date,
                'first_in' => $card?->first_in?->timezone(config('app.timezone'))->format('H:i') ?? '',
                'last_out' => $card?->last_out?->timezone(config('app.timezone'))->format('H:i') ?? '',
                'day' => $this->dayLabel($card),
                'day_value' => $card === null ? '' : number_format((float) $card->day_value, 2),
                'late' => $card === null ? '' : (string) $late,
                'ot' => $card === null ? '' : (string) $ot,
                'timecard_id' => $card?->id,
                'can_waive' => $card !== null && ! $card->late_waived && $late > 0,
                'can_ot' => $card !== null && $card->exception === null && $card->ot_approved_minutes === null && $ot > 0,
                'pending' => $pending->has($date),
            ];
            $cursor->addDay();
        }

        return $rows;
    }

    private function dayLabel(?Timecard $card): string
    {
        if ($card === null) {
            return '';
        }

        return match ($card->exception) {
            TimecardException::MISSING_PUNCH => 'Missing punch',
            TimecardException::NO_PROFILE => 'No time profile',
            TimecardException::SHORT_PRESENCE => 'Too little presence',
            default => match ($card->day_type) {
                DayType::FULL => 'Full',
                DayType::HALF => 'Half',
                DayType::SHORT => 'Short',
                default => 'None',
            },
        };
    }

    /** @return array{punches: Collection<int, Punch>, adjustments: Collection<int, AttendanceAdjustment>} */
    private function detail(Employment $employment, string $date): array
    {
        [$from, $to] = WorkDate::window($date);

        return [
            'punches' => Punch::query()
                ->with('device')
                ->where('employment_id', $employment->id)
                ->where('punched_at', '>=', $from)
                ->where('punched_at', '<', $to)
                ->orderBy('punched_at')
                ->get(),
            'adjustments' => AttendanceAdjustment::query()
                ->where('employment_id', $employment->id)
                ->whereDate('work_date', $date)
                ->orderBy('id')
                ->get(),
        ];
    }

    public function adjustmentLabel(AdjustmentType $type): string
    {
        return match ($type) {
            AdjustmentType::ADD_PUNCH => 'Added punch',
            AdjustmentType::VOID_PUNCH => 'Void punch',
        };
    }

    private function monthStart(): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        } catch (\Throwable) {
            $this->month = now()->format('Y-m');

            return now()->startOfMonth();
        }
    }
}
