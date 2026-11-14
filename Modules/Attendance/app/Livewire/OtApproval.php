<?php

namespace Modules\Attendance\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Attendance\Models\Timecard;
use Modules\Attendance\Services\OtApprovalService;
use Modules\Attendance\Support\AttendanceNav;
use Modules\Calendar\Models\PayPeriod;

class OtApproval extends Component
{
    public ?int $periodId = null;

    public string $state = 'undecided';

    /** @var list<int|string> */
    public array $selected = [];

    public string $partMinutes = '';

    public string $partReason = '';

    public ?int $activeId = null;

    public string $error = '';

    public string $notice = '';

    public function mount(): void
    {
        $this->authorize('ot.approve');
        $this->periodId = $this->periodContainingToday();
    }

    public function openPart(int $timecardId): void
    {
        $this->authorize('ot.approve');
        $this->activeId = $timecardId;
        $this->partMinutes = '';
        $this->partReason = '';
        $this->error = '';
    }

    public function approveAll(int $timecardId): void
    {
        $this->decide($timecardId, null, null);
    }

    public function approvePart(): void
    {
        $this->authorize('ot.approve');
        $card = Timecard::query()->findOrFail($this->activeId);
        $minutes = (int) $this->partMinutes;
        $computed = app(OtApprovalService::class)->computed($card);

        if ($minutes < $computed && trim($this->partReason) === '') {
            $this->error = 'A reason is required when approving less than computed OT.';

            return;
        }

        $this->decide($card->id, $minutes, $this->partReason);
        if ($this->error === '') {
            $this->activeId = null;
        }
    }

    public function refuse(int $timecardId): void
    {
        $this->authorize('ot.approve');

        try {
            $card = Timecard::query()->findOrFail($timecardId);
            app(OtApprovalService::class)->refuse($card, $this->user(), $this->partReason);
            $this->notice = 'OT refused.';
            $this->error = '';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function resetDecision(int $timecardId): void
    {
        $this->authorize('ot.approve');

        try {
            $card = Timecard::query()->findOrFail($timecardId);
            app(OtApprovalService::class)->reset($card, $this->user());
            $this->notice = 'OT reset.';
            $this->error = '';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function approveSelected(): void
    {
        $this->authorize('ot.approve');
        $ids = array_map(fn ($id) => (int) $id, $this->selected);
        $result = app(OtApprovalService::class)->approveMany($ids, $this->user());
        $skipped = count($result['skipped']);
        $this->notice = 'Approved '.$result['approved'].', skipped '.$skipped.'.';
        foreach ($result['skipped'] as $id => $reason) {
            $this->notice .= ' #'.$id.': '.$reason;
        }
        $this->selected = [];
    }

    public function render(): View
    {
        $this->authorize('ot.approve');
        $period = $this->periodId ? PayPeriod::query()->find($this->periodId) : null;
        $rows = $period === null ? collect() : $this->rows($period);
        $approvedMinutes = (int) $rows->sum(fn (Timecard $card) => (int) ($card->ot_approved_minutes ?? 0));

        return view('attendance::livewire.ot-approval', [
            'periods' => PayPeriod::query()->orderByDesc('year')->orderByDesc('month')->get(),
            'rows' => $rows,
            'otHours' => number_format($approvedMinutes / 60, 2),
        ])->layout('components.layouts.module', [
            'moduleName' => 'Time & Leave',
            'title' => 'OT approval',
            'moduleNav' => AttendanceNav::items('ot'),
        ]);
    }

    /** @return Collection<int, Timecard> */
    private function rows(PayPeriod $period): Collection
    {
        $query = Timecard::query()
            ->with('employment.person')
            ->where('pay_period_id', $period->id)
            ->whereRaw('(ot_morning_minutes + ot_evening_minutes) > 0')
            ->orderBy('work_date')
            ->orderBy('employment_id');

        if ($this->state === 'undecided') {
            $query->whereNull('ot_approved_minutes');
        } elseif ($this->state === 'approved') {
            $query->where('ot_approved_minutes', '>', 0);
        } elseif ($this->state === 'refused') {
            $query->where('ot_approved_minutes', 0);
        }

        return $query->get();
    }

    private function decide(int $timecardId, ?int $minutes, ?string $reason): void
    {
        $this->authorize('ot.approve');

        try {
            $card = Timecard::query()->findOrFail($timecardId);
            app(OtApprovalService::class)->approve($card, $minutes, $this->user(), $reason);
            $this->notice = 'OT approved.';
            $this->error = '';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    private function periodContainingToday(): ?int
    {
        $today = Carbon::now(config('app.timezone'))->toDateString();

        return PayPeriod::query()
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderBy('id')
            ->value('id');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
