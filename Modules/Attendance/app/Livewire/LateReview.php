<?php

namespace Modules\Attendance\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Attendance\Models\Timecard;
use Modules\Attendance\Services\LateWaiverService;
use Modules\Attendance\Services\SummaryService;
use Modules\Attendance\Support\AttendanceNav;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;

class LateReview extends Component
{
    public ?int $periodId = null;

    public string $reason = '';

    public ?int $activeId = null;

    public string $error = '';

    public string $notice = '';

    public function mount(): void
    {
        $this->authorize('timecard.view');
        $today = Carbon::now(config('app.timezone'))->toDateString();
        $this->periodId = PayPeriod::query()
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderBy('id')
            ->value('id');
    }

    public function openWaive(int $timecardId): void
    {
        $this->authorize('late.waive');
        $this->activeId = $timecardId;
        $this->reason = '';
        $this->error = '';
    }

    public function saveWaive(): void
    {
        $this->authorize('late.waive');

        try {
            $card = Timecard::query()->findOrFail($this->activeId);
            app(LateWaiverService::class)->waive($card, $this->user(), $this->reason);
            $this->activeId = null;
            $this->notice = 'Late minutes waived.';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(): View
    {
        $this->authorize('timecard.view');
        $period = $this->periodId ? PayPeriod::query()->find($this->periodId) : null;

        return view('attendance::livewire.late-review', [
            'periods' => PayPeriod::query()->orderByDesc('year')->orderByDesc('month')->get(),
            'groups' => $period === null ? [] : $this->groups($period),
        ])->layout('components.layouts.module', [
            'moduleName' => 'Time & Leave',
            'title' => 'Late review',
            'moduleNav' => AttendanceNav::items('late'),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function groups(PayPeriod $period): array
    {
        $cards = Timecard::query()
            ->with('employment.person')
            ->where('pay_period_id', $period->id)
            ->where(function ($query) {
                $query->where('late_in_minutes', '>', 0)
                    ->orWhere('early_out_minutes', '>', 0)
                    ->orWhere('late_waived', true);
            })
            ->orderBy('employment_id')
            ->orderBy('work_date')
            ->get()
            ->groupBy('employment_id');

        $summaries = app(SummaryService::class);
        $groups = [];

        foreach ($cards as $employmentId => $rows) {
            $employment = $rows->first()?->employment ?? Employment::query()->find($employmentId);
            if ($employment === null) {
                continue;
            }

            try {
                $chargeable = $summaries->current($employment, $period)->lateMinutesChargeable;
            } catch (DomainException) {
                $chargeable = 0;
            }

            $groups[] = [
                'employment' => $employment,
                'name' => (string) $employment->person?->name_with_initials,
                'employee_no' => (string) $employment->employee_no,
                'chargeable' => $chargeable,
                'rows' => $rows,
            ];
        }

        return $groups;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
