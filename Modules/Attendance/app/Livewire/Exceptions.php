<?php

namespace Modules\Attendance\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Enums\TimecardException;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Models\Shift;
use Modules\Attendance\Models\Timecard;
use Modules\Attendance\Services\AdjustmentService;
use Modules\Attendance\Services\TimeProfileService;
use Modules\Attendance\Support\AttendanceNav;
use Modules\Attendance\Timecards\WorkDate;
use Modules\Calendar\Enums\PeriodStatus;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;

class Exceptions extends Component
{
    public ?int $periodId = null;

    public string $tab = 'missing';

    /** @var list<int|string> */
    public array $selected = [];

    public string $punchedAt = '';

    public string $reason = '';

    public string $bulkReason = '';

    public ?int $activeTimecardId = null;

    public string $error = '';

    public string $notice = '';

    public function mount(): void
    {
        $this->authorize('attendance.adjust');
        $this->periodId = PayPeriod::query()
            ->where('status', PeriodStatus::OPEN)
            ->orderBy('year')
            ->orderBy('month')
            ->value('id');
    }

    public function openAdd(int $timecardId): void
    {
        $this->authorize('attendance.adjust');
        $card = Timecard::query()->findOrFail($timecardId);
        $this->activeTimecardId = $card->id;
        $this->punchedAt = $this->suggest($card);
        $this->reason = '';
        $this->error = '';
    }

    public function saveAdd(): void
    {
        $this->authorize('attendance.adjust');
        $card = Timecard::query()->findOrFail($this->activeTimecardId);

        try {
            app(AdjustmentService::class)->addPunch($card->employment, $this->punchedAt, $this->reason, $this->user());
            $this->activeTimecardId = null;
            $this->notice = 'Punch added.';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function addSelected(): void
    {
        $this->authorize('attendance.adjust');
        $made = 0;

        try {
            foreach ($this->selected as $id) {
                $card = Timecard::query()->findOrFail((int) $id);
                app(AdjustmentService::class)->addPunch($card->employment, $this->suggest($card), $this->bulkReason, $this->user());
                $made++;
            }
            $this->selected = [];
            $this->notice = $made.' punch'.($made === 1 ? '' : 'es').' added.';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(): View
    {
        $this->authorize('attendance.adjust');
        $period = $this->periodId ? PayPeriod::query()->find($this->periodId) : null;
        $counts = $this->counts($period);

        return view('attendance::livewire.exceptions', [
            'periods' => PayPeriod::query()->where('status', PeriodStatus::OPEN)->orderBy('year')->orderBy('month')->get(),
            'period' => $period,
            'counts' => $counts,
            'rows' => $period === null ? collect() : $this->rows($period),
        ])->layout('components.layouts.module', [
            'moduleName' => 'Time & Leave',
            'title' => 'Exceptions',
            'moduleNav' => AttendanceNav::items('exceptions'),
        ]);
    }

    /** @return array<string, int> */
    private function counts(?PayPeriod $period): array
    {
        if ($period === null) {
            return ['missing' => 0, 'profile' => 0, 'short' => 0, 'unidentified' => 0, 'pending' => 0];
        }

        $missing = Timecard::query()
            ->where('pay_period_id', $period->id)
            ->where('exception', TimecardException::MISSING_PUNCH)
            ->count();
        $short = Timecard::query()
            ->where('pay_period_id', $period->id)
            ->where('exception', TimecardException::SHORT_PRESENCE)
            ->count();

        return [
            'missing' => $missing,
            'profile' => $this->withoutProfile($period)->count(),
            'short' => $short,
            'unidentified' => $this->unidentified($period),
            'pending' => app(AdjustmentService::class)->pendingCount($period),
        ];
    }

    /** @return Collection<int, mixed> */
    private function rows(PayPeriod $period): Collection
    {
        if ($this->tab === 'profile') {
            return $this->withoutProfile($period)->map(fn (Employment $employment) => [
                'kind' => 'profile',
                'employment_id' => $employment->id,
                'employee_no' => $employment->employee_no,
                'name' => $employment->person?->name_with_initials,
                'href' => route('employees.show', $employment).'?tab=time',
            ]);
        }

        if ($this->tab === 'short') {
            return $this->timecardRows($period, TimecardException::SHORT_PRESENCE);
        }

        if ($this->tab === 'missing') {
            return $this->timecardRows($period, TimecardException::MISSING_PUNCH);
        }

        return collect();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function timecardRows(PayPeriod $period, TimecardException $exception): Collection
    {
        return Timecard::query()
            ->with('employment.person')
            ->where('pay_period_id', $period->id)
            ->where('exception', $exception)
            ->orderBy('work_date')
            ->orderBy('employment_id')
            ->get()
            ->map(fn (Timecard $card) => [
                'kind' => 'timecard',
                'id' => $card->id,
                'employment_id' => $card->employment_id,
                'employee_no' => $card->employment?->employee_no,
                'name' => $card->employment?->person?->name_with_initials,
                'date' => $card->work_date->toDateString(),
                'first_in' => $card->first_in?->timezone(config('app.timezone'))->format('H:i') ?? '',
            ]);
    }

    /** @return Collection<int, Employment> */
    private function withoutProfile(PayPeriod $period): Collection
    {
        $profiles = app(TimeProfileService::class);
        $end = Carbon::parse($period->end_date)->toDateString();

        return Employment::query()
            ->with('person')
            ->where('company_id', $period->company_id)
            ->whereDate('hire_date', '<=', $end)
            ->where(function ($query) use ($period) {
                $query->whereNull('exit_date')->orWhereDate('exit_date', '>=', Carbon::parse($period->start_date)->toDateString());
            })
            ->orderBy('employee_no')
            ->get()
            ->filter(function (Employment $employment) use ($profiles, $end) {
                $exit = $employment->exit_date !== null ? Carbon::parse($employment->exit_date)->toDateString() : $end;
                $hire = Carbon::parse($employment->hire_date)->toDateString();
                $on = min($end, $exit);
                if ($hire > $on) {
                    $on = $end;
                }

                return $profiles->on($employment, $on) === null;
            })
            ->values();
    }

    private function unidentified(PayPeriod $period): int
    {
        $start = Carbon::parse($period->start_date)->toDateString();
        $end = Carbon::parse($period->end_date)->toDateString();
        [$windowStart] = WorkDate::window($start);
        [, $windowEnd] = WorkDate::window($end);

        return Punch::query()
            ->where('resolution', PunchResolution::UNRESOLVED)
            ->where('punched_at', '>=', $windowStart)
            ->where('punched_at', '<', $windowEnd)
            ->count();
    }

    private function suggest(Timecard $card): string
    {
        $date = Carbon::parse($card->work_date)->toDateString();
        [$from, $to] = WorkDate::window($date);
        $punch = Punch::query()
            ->where('employment_id', $card->employment_id)
            ->where('resolution', PunchResolution::RESOLVED)
            ->where('punched_at', '>=', $from)
            ->where('punched_at', '<', $to)
            ->orderBy('punched_at')
            ->first();

        $profile = app(TimeProfileService::class)->on((int) $card->employment_id, $date);
        $profile?->loadMissing('shift');
        $shift = $profile?->shift ?? ($card->shift_id ? Shift::query()->find($card->shift_id) : null);
        $hour = $punch === null ? 0 : Carbon::parse($punch->punched_at)->timezone(config('app.timezone'))->hour;
        $useEnd = $punch === null || $hour < 12;
        $raw = $useEnd ? ($shift?->end_time ?? '17:00:00') : ($shift?->start_time ?? '08:00:00');

        return $date.' '.substr((string) $raw, 0, 5);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
