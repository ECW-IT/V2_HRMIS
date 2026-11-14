<?php

namespace Modules\Leave\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\Leave\Enums\LedgerEntryKind;
use Modules\Leave\Models\LeaveLedgerEntry;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveBalanceService;

class EmployeeLeave extends Component
{
    public int $employmentId = 0;

    public string $month = '';

    public bool $showAdjust = false;

    public string $leaveTypeId = '';

    public string $days = '';

    public string $note = '';

    public string $error = '';

    public function mount(Employment $employment): void
    {
        $this->authorize('leave.view');
        $this->employmentId = $employment->id;
        $this->month = now()->format('Y-m');
    }

    public function openAdjust(): void
    {
        $this->authorize('leave.manage');
        $this->showAdjust = true;
        $this->error = '';
    }

    public function saveAdjust(): void
    {
        $this->authorize('leave.manage');
        $employment = $this->employment();
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();

        try {
            $period = app(PayPeriodService::class)->ensure($employment->company, (int) $start->year, (int) $start->month);
            app(LeaveBalanceService::class)->adjust(
                $employment,
                (int) $this->leaveTypeId,
                $period,
                (float) $this->days,
                $this->note,
                $this->user(),
            );
            $this->showAdjust = false;
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(): View
    {
        $this->authorize('leave.view');
        $employment = $this->employment()->load('person');
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $period = app(PayPeriodService::class)->ensure($employment->company, (int) $start->year, (int) $start->month);
        $balances = app(LeaveBalanceService::class);

        $lines = [];
        foreach (LeaveType::query()->where('is_active', true)->orderBy('code')->get() as $type) {
            $balance = $balances->balance($employment, $type->id, $period);
            $entries = LeaveLedgerEntry::query()
                ->where('employment_id', $employment->id)
                ->where('leave_type_id', $type->id)
                ->where('pay_period_id', $period->id)
                ->orderBy('id')
                ->get();

            if ($entries->isEmpty()) {
                continue;
            }

            $lines[] = [
                'type' => $type->name,
                'balance' => $this->days($balance),
                'entries' => $entries->map(fn (LeaveLedgerEntry $entry) => [
                    'kind' => $this->kindLabel($entry->kind),
                    'days' => $this->days((float) $entry->days),
                    'note' => (string) ($entry->note ?? ''),
                ])->all(),
            ];
        }

        return view('leave::livewire.employee-leave', [
            'employment' => $employment,
            'requests' => LeaveRequest::query()
                ->with('leaveType')
                ->where('employment_id', $employment->id)
                ->orderByDesc('date')
                ->get(),
            'lines' => $lines,
            'types' => LeaveType::query()->where('is_active', true)->orderBy('code')->get(),
            'periodLabel' => $start->format('F Y'),
        ]);
    }

    private function kindLabel(LedgerEntryKind|string $kind): string
    {
        $value = $kind instanceof LedgerEntryKind ? $kind : LedgerEntryKind::from($kind);

        return match ($value) {
            LedgerEntryKind::GRANT => 'Granted',
            LedgerEntryKind::TAKEN => 'Taken',
            LedgerEntryKind::RESTORED => 'Restored',
            LedgerEntryKind::ADJUSTMENT => 'Adjusted',
        };
    }

    private function days(float $value): string
    {
        return number_format($value, 2);
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
}
