<?php

namespace Modules\Leave\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Attendance\Support\AttendanceNav;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveBalanceService;
use Modules\Leave\Services\LeaveService;

class LeaveRequests extends Component
{
    /** @var list<string> */
    public array $statuses = ['PENDING', 'APPROVED'];

    public bool $showForm = false;

    public string $employmentId = '';

    public string $leaveTypeId = '';

    public string $from = '';

    public string $to = '';

    /** @var array<string, bool> */
    public array $dates = [];

    public string $reason = '';

    public bool $preApproved = false;

    public string $error = '';

    public function mount(): void
    {
        $this->authorize('leave.view');
    }

    public function openForm(): void
    {
        $this->authorize('leave.request');
        $this->showForm = true;
        $this->error = '';
        $this->preApproved = false;
    }

    public function updatedFrom(): void
    {
        $this->fillDates();
    }

    public function updatedTo(): void
    {
        $this->fillDates();
    }

    public function save(): void
    {
        $this->authorize('leave.request');
        $this->fillDates();
        $skip = [];
        foreach ($this->dates as $date => $ticked) {
            if (! $ticked) {
                $skip[] = $date;
            }
        }

        $opts = [];
        if (trim($this->reason) !== '') {
            $opts['reason'] = trim($this->reason);
        }
        if ($this->preApproved) {
            $opts['pre_approved'] = true;
        }

        try {
            app(LeaveService::class)->requestRange(
                Employment::query()->findOrFail((int) $this->employmentId),
                (int) $this->leaveTypeId,
                $this->from,
                $this->to,
                $this->user(),
                $opts,
                $skip,
            );
            $this->showForm = false;
            $this->error = '';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function cancel(int $requestId): void
    {
        try {
            $request = LeaveRequest::query()->findOrFail($requestId);
            app(LeaveService::class)->cancel($request, $this->user());
        } catch (DomainException|AuthorizationException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(): View
    {
        $this->authorize('leave.view');
        $query = LeaveRequest::query()->with(['employment.person', 'leaveType'])->orderByDesc('date');
        if ($this->statuses !== []) {
            $query->whereIn('status', $this->statuses);
        } else {
            $query->whereRaw('1 = 0');
        }

        return view('leave::livewire.leave-requests', [
            'requests' => $query->get(),
            'employments' => Employment::query()->with('person')->orderBy('employee_no')->get(),
            'types' => LeaveType::query()->where('is_active', true)->orderBy('code')->get(),
            'balances' => $this->balances(),
        ])->layout('components.layouts.module', [
            'moduleName' => 'Time & Leave',
            'title' => 'Leave requests',
            'moduleNav' => AttendanceNav::items('leave-requests'),
        ]);
    }

    /** @return list<array{label: string, available: string}> */
    private function balances(): array
    {
        if ($this->employmentId === '' || $this->leaveTypeId === '' || $this->dates === []) {
            return [];
        }

        $employment = Employment::query()->find((int) $this->employmentId);
        if ($employment === null) {
            return [];
        }

        $balances = app(LeaveBalanceService::class);
        $periods = app(PayPeriodService::class);
        $seen = [];
        $rows = [];

        foreach (array_keys($this->dates) as $date) {
            $day = Carbon::parse($date);
            $key = $day->format('Y-m');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $period = $periods->ensure($employment->company, (int) $day->year, (int) $day->month);
            $available = $balances->available($employment, (int) $this->leaveTypeId, $period);
            $rows[] = [
                'label' => $day->format('F Y'),
                'available' => $this->days($available),
            ];
        }

        return $rows;
    }

    private function fillDates(): void
    {
        if ($this->from === '' || $this->to === '') {
            return;
        }

        try {
            $from = Carbon::parse($this->from)->startOfDay();
            $to = Carbon::parse($this->to)->startOfDay();
        } catch (\Throwable) {
            return;
        }

        if ($to->lt($from) || $from->diffInDays($to) > 62) {
            return;
        }

        $dates = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();
            $dates[$key] = array_key_exists($key, $this->dates) ? (bool) $this->dates[$key] : true;
            $cursor->addDay();
        }
        $this->dates = $dates;
    }

    private function days(float $value): string
    {
        return fmod($value, 1.0) === 0.0 ? (string) (int) $value : number_format($value, 2);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
