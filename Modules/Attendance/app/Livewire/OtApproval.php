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

    public bool $partBulkOpen = false;

    public bool $refuseBulkOpen = false;

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
        $this->partBulkOpen = false;
        $this->refuseBulkOpen = false;
        $this->partMinutes = '';
        $this->partReason = '';
        $this->error = '';
    }

    public function closePart(): void
    {
        $this->activeId = null;
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
        $result = app(OtApprovalService::class)->approveMany($this->selectedIds(), $this->user());
        $this->reportBulk('Approved', $result);
    }

    public function approvePartSelected(): void
    {
        $this->authorize('ot.approve');
        $result = app(OtApprovalService::class)->approveMany(
            $this->selectedIds(),
            $this->user(),
            (int) $this->partMinutes,
            $this->partReason,
        );
        $this->reportBulk('Approved', $result);
        $this->partBulkOpen = false;
    }

    public function openPartSelected(): void
    {
        $this->authorize('ot.approve');

        if ($this->selected === []) {
            $this->error = 'Tick at least one row.';
            $this->partBulkOpen = false;

            return;
        }

        $this->activeId = null;
        $this->refuseBulkOpen = false;
        $this->partMinutes = '';
        $this->partReason = '';
        $this->error = '';
        $this->partBulkOpen = true;
    }

    public function closePartSelected(): void
    {
        $this->partBulkOpen = false;
    }

    public function refuseSelected(): void
    {
        $this->authorize('ot.approve');

        if (trim($this->partReason) === '') {
            $this->error = 'A reason is required to refuse OT.';

            return;
        }

        $result = app(OtApprovalService::class)->refuseMany($this->selectedIds(), $this->user(), $this->partReason);
        $this->reportBulk('Refused', $result);
        $this->refuseBulkOpen = false;
    }

    public function openRefuseSelected(): void
    {
        $this->authorize('ot.approve');

        if ($this->selected === []) {
            $this->error = 'Tick at least one row.';
            $this->refuseBulkOpen = false;

            return;
        }

        $this->activeId = null;
        $this->partBulkOpen = false;
        $this->partMinutes = '';
        $this->partReason = '';
        $this->error = '';
        $this->refuseBulkOpen = true;
    }

    public function closeRefuseSelected(): void
    {
        $this->refuseBulkOpen = false;
    }

    public function resetSelected(): void
    {
        $this->authorize('ot.approve');
        $result = app(OtApprovalService::class)->resetMany($this->selectedIds(), $this->user());
        $this->reportBulk('Reset', $result);
    }

    public function toggleAll(): void
    {
        $this->authorize('ot.approve');
        $visibleIds = $this->visibleIds();
        $this->selected = $this->allVisibleSelected($visibleIds) ? [] : $visibleIds;
    }

    public function updatedPeriodId(): void
    {
        $this->selected = [];
        $this->partBulkOpen = false;
        $this->refuseBulkOpen = false;
    }

    public function updatedState(): void
    {
        $this->selected = [];
        $this->partBulkOpen = false;
        $this->refuseBulkOpen = false;
    }

    public function render(): View
    {
        $this->authorize('ot.approve');
        $period = $this->periodId ? PayPeriod::query()->find($this->periodId) : null;
        $rows = $period === null ? collect() : $this->rows($period);
        $approvedMinutes = (int) $rows->sum(fn (Timecard $card) => (int) ($card->ot_approved_minutes ?? 0));
        $visibleIds = $rows->pluck('id')->map(fn ($id) => (string) $id)->all();

        return view('attendance::livewire.ot-approval', [
            'periods' => PayPeriod::query()->orderByDesc('year')->orderByDesc('month')->get(),
            'rows' => $rows,
            'otHours' => number_format($approvedMinutes / 60, 2),
            'allSelected' => $this->allVisibleSelected($visibleIds),
        ])->layout('components.layouts.module', [
            'moduleName' => 'Time & Leave',
            'title' => 'OT approval',
            'moduleNav' => AttendanceNav::items('ot'),
        ]);
    }

    /** @return list<int> */
    private function selectedIds(): array
    {
        return array_map(fn ($id) => (int) $id, $this->selected);
    }

    /** @param  array{approved: int, skipped: array<int, string>}  $result */
    private function reportBulk(string $done, array $result): void
    {
        $skipped = count($result['skipped']);
        $this->notice = $done.' '.$result['approved'].', skipped '.$skipped.'.';
        foreach ($result['skipped'] as $id => $reason) {
            $this->notice .= ' #'.$id.': '.$reason;
        }
        $this->error = '';
        $this->selected = [];
    }

    /** @return list<string> */
    private function visibleIds(): array
    {
        $period = $this->periodId ? PayPeriod::query()->find($this->periodId) : null;

        if ($period === null) {
            return [];
        }

        return $this->rows($period)->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /** @param list<string> $visibleIds */
    private function allVisibleSelected(array $visibleIds): bool
    {
        return $visibleIds !== [] && array_diff($visibleIds, array_map('strval', $this->selected)) === [];
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
