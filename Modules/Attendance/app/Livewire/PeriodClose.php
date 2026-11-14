<?php

namespace Modules\Attendance\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Attendance\Models\PeriodEvent;
use Modules\Attendance\Services\PeriodCloseService;
use Modules\Attendance\Services\TimecardService;
use Modules\Attendance\Support\AttendanceNav;
use Modules\Attendance\Support\ChecklistItem;
use Modules\Calendar\Enums\PeriodStatus;
use Modules\Calendar\Models\PayPeriod;

class PeriodClose extends Component
{
    public ?int $periodId = null;

    public bool $confirming = false;

    public string $reopenReason = '';

    public string $error = '';

    public string $notice = '';

    public function mount(): void
    {
        $this->authorize('period.manage');
        $open = PayPeriod::query()
            ->where('status', PeriodStatus::OPEN)
            ->orderBy('year')
            ->orderBy('month')
            ->value('id');
        $this->periodId = $open ?? PayPeriod::query()->orderByDesc('year')->orderByDesc('month')->value('id');
    }

    public function refreshChecklist(): void
    {
        $this->authorize('period.manage');
        $period = $this->period();
        app(TimecardService::class)->computeRange((int) $period->company_id, $period->start_date, $period->end_date);
        $this->notice = 'Timecards refreshed.';
    }

    public function askClose(): void
    {
        $this->authorize('period.close');
        $this->error = '';
        $this->confirming = true;
    }

    public function closePeriod(): void
    {
        $this->authorize('period.close');

        try {
            app(PeriodCloseService::class)->close($this->period(), $this->user());
            $this->confirming = false;
            $this->notice = 'The period is closed.';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
            $this->confirming = false;
        }
    }

    public function reopenPeriod(): void
    {
        $this->authorize('period.reopen');

        try {
            app(PeriodCloseService::class)->reopen($this->period(), $this->user(), $this->reopenReason);
            $this->reopenReason = '';
            $this->notice = 'The period is open again.';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(): View
    {
        $this->authorize('period.manage');
        $period = $this->periodId ? PayPeriod::query()->with('company')->find($this->periodId) : null;
        $items = $period === null ? [] : app(PeriodCloseService::class)->checklist($period);
        $blockingClear = collect($items)->every(fn (ChecklistItem $item) => ! $item->blocking || $item->ok);
        $canClose = $period !== null
            && $period->status === PeriodStatus::OPEN
            && $blockingClear
            && Auth::user()?->can('period.close');

        $closer = null;
        if ($period?->closed_by) {
            $closer = User::query()->find($period->closed_by)?->name;
        }

        return view('attendance::livewire.period-close', [
            'periods' => PayPeriod::query()->orderBy('year')->orderBy('month')->get(),
            'period' => $period,
            'items' => $items,
            'links' => $this->links(),
            'canClose' => $canClose,
            'confirmText' => $period === null ? '' : $this->confirmText($period),
            'closer' => $closer,
            'events' => $period === null ? collect() : PeriodEvent::query()->where('pay_period_id', $period->id)->orderBy('id')->get(),
        ])->layout('components.layouts.module', [
            'moduleName' => 'Time & Leave',
            'title' => 'Period close',
            'moduleNav' => AttendanceNav::items('period-close'),
        ]);
    }

    private function period(): PayPeriod
    {
        return PayPeriod::query()->findOrFail($this->periodId);
    }

    private function confirmText(PayPeriod $period): string
    {
        $period->loadMissing('company');
        $label = Carbon::create((int) $period->year, (int) $period->month, 1)->format('F Y');

        return 'Closing locks '.$label.' for '.$period->company->name.'. Timecards, leave and adjustments in this month can no longer be changed.';
    }

    /** @return array<string, string> */
    private function links(): array
    {
        return [
            'C03' => route('calendar.expected-days.index'),
            'C04' => route('attendance.exceptions'),
            'C05' => route('attendance.exceptions'),
            'C06' => route('approvals.index'),
            'C07' => route('attendance.ot'),
            'C08' => route('leave.requests.index'),
            'C09' => route('attendance.unresolved.index'),
            'W01' => route('attendance.exceptions'),
        ];
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
