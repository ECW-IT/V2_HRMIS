<?php

namespace Modules\Attendance\Livewire\Tabs;

use Carbon\Carbon;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Attendance\Enums\AttendanceMode;
use Modules\Attendance\Enums\OtMode;
use Modules\Attendance\Exceptions\InvalidTimeProfile;
use Modules\Attendance\Models\EmploymentTimeProfile;
use Modules\Attendance\Models\Shift;
use Modules\Attendance\Services\TimeProfileService;
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\EmployeeCategory;

class TimeProfile extends Component
{
    public Employment $employment;

    public bool $showForm = false;

    public string $effective_from = '';

    public string|int|null $pay_period_id = null;

    public string|int|null $shift_id = null;

    public string $attendance_mode = 'PUNCH';

    public string $ot_mode = 'DEFAULT';

    public string $late_deduction = 'DEFAULT';

    public string $break_available = 'DEFAULT';

    public bool $use_custom_times = false;

    public string $start_time = '';

    public string $end_time = '';

    public string $late_from = '';

    public string $morning_cutoff = '';

    public string $half_day_from = '';

    public string $early_leave_from = '';

    /** @var list<array{name: string, start_time: string, end_time: string, is_paid: bool}> */
    public array $breaks = [];

    public string $error = '';

    public string $message = '';

    public function mount(Employment $employment): void
    {
        $this->authorize('employee.view');
        $this->employment = $employment;
        $this->effective_from = now(config('app.timezone'))->toDateString();
    }

    public function openChange(): void
    {
        $this->authorize('employee.update');
        $this->ensureNearbyPeriods();

        $current = app(TimeProfileService::class)->on($this->employment, now(config('app.timezone'))->toDateString());
        $current?->load('breaks');

        $this->shift_id = $current?->shift_id;
        $this->attendance_mode = $current?->attendance_mode->value ?? AttendanceMode::PUNCH->value;
        $this->ot_mode = $current?->ot_mode?->value ?? 'DEFAULT';
        $this->late_deduction = $current?->late_deduction === null ? 'DEFAULT' : ($current->late_deduction ? 'YES' : 'NO');
        $this->break_available = $current?->break_available === null ? 'DEFAULT' : ($current->break_available ? 'YES' : 'NO');
        $this->effective_from = now(config('app.timezone'))->toDateString();
        $this->pay_period_id = null;
        $this->use_custom_times = (bool) $current?->uses_custom_times;
        $this->error = '';

        if ($current !== null && $current->uses_custom_times) {
            $this->fillFromProfile($current);
        } elseif ($current?->shift !== null) {
            $this->fillFromShift($current->shift);
        } else {
            $this->clearTimes();
        }

        $this->showForm = true;
    }

    public function updatedPayPeriodId(): void
    {
        if ($this->pay_period_id === null || $this->pay_period_id === '') {
            return;
        }

        $period = PayPeriod::withoutGlobalScopes()->find($this->pay_period_id);
        if ($period !== null) {
            $this->effective_from = $period->start_date->toDateString();
        }
    }

    public function updatedShiftId(): void
    {
        $shift = Shift::query()->with('breaks')->find($this->shift_id);
        if ($shift !== null) {
            $this->fillFromShift($shift);
        }
    }

    public function addBreakRow(): void
    {
        $this->breaks[] = ['name' => '', 'start_time' => '20:00', 'end_time' => '20:30', 'is_paid' => false];
    }

    public function removeBreakRow(int $index): void
    {
        unset($this->breaks[$index]);
        $this->breaks = array_values($this->breaks);
    }

    public function save(TimeProfileService $service): void
    {
        $this->authorize('employee.update');
        $this->error = '';
        $this->message = '';

        $rules = [
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'attendance_mode' => ['required'],
            'ot_mode' => ['required', 'in:DEFAULT,NONE,MORNING,EVENING,BOTH'],
            'late_deduction' => ['required', 'in:DEFAULT,YES,NO'],
            'break_available' => ['required', 'in:DEFAULT,YES,NO'],
        ];

        if ($this->use_custom_times) {
            $rules = array_merge($rules, $this->timeRules());
        }

        $validated = $this->validate($rules);

        $payload = [
            'effective_from' => $validated['effective_from'],
            'shift_id' => (int) $validated['shift_id'],
            'attendance_mode' => $validated['attendance_mode'],
            'ot_mode' => $validated['ot_mode'],
            'late_deduction' => $validated['late_deduction'],
            'break_available' => $validated['break_available'],
            'times' => null,
        ];

        if ($this->use_custom_times) {
            $payload['times'] = $this->timePayload();
            $payload['breaks'] = $validated['breaks'] ?? [];
        }

        try {
            $service->set($this->employment, $payload);
            $this->showForm = false;
            $this->message = 'Time profile saved.';
        } catch (InvalidTimeProfile $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(): View
    {
        $this->authorize('employee.view');

        $history = EmploymentTimeProfile::query()
            ->with('shift')
            ->where('employment_id', $this->employment->id)
            ->orderByDesc('effective_from')
            ->get();

        $current = app(TimeProfileService::class)->on(
            $this->employment,
            now(config('app.timezone'))->toDateString(),
        );
        $current?->load('breaks');

        $category = $this->employment->employee_category_id === null
            ? null
            : EmployeeCategory::query()->with('breaks')->find($this->employment->employee_category_id);

        return view('attendance::livewire.tabs.time-profile', [
            'current' => $current,
            'categoryTimeStatus' => $category?->timeRuleSummary() ?? '',
            'history' => $history,
            'shifts' => Shift::query()->where('is_active', true)->orderBy('code')->get(),
            'attendanceModes' => AttendanceMode::cases(),
            'otModes' => OtMode::cases(),
            'payPeriods' => PayPeriod::withoutGlobalScopes()
                ->where('company_id', $this->employment->company_id)
                ->orderByDesc('start_date')
                ->get(),
        ]);
    }

    private function ensureNearbyPeriods(): void
    {
        $company = Company::query()->find($this->employment->company_id);
        if ($company === null) {
            return;
        }

        $now = Carbon::now(config('app.timezone'));
        $next = $now->copy()->addMonth();
        $periods = app(PayPeriodService::class);
        $periods->ensure($company, (int) $now->year, (int) $now->month);
        $periods->ensure($company, (int) $next->year, (int) $next->month);
    }

    private function fillFromProfile(EmploymentTimeProfile $profile): void
    {
        $this->start_time = $this->clock($profile->start_time);
        $this->end_time = $this->clock($profile->end_time);
        $this->late_from = $this->clock($profile->late_from);
        $this->morning_cutoff = $this->clock($profile->morning_cutoff);
        $this->half_day_from = $this->clock($profile->half_day_from);
        $this->early_leave_from = $this->clock($profile->early_leave_from);
        $this->breaks = $profile->breaks->map(fn ($break) => [
            'name' => $break->name,
            'start_time' => $this->clock($break->start_time),
            'end_time' => $this->clock($break->end_time),
            'is_paid' => (bool) $break->is_paid,
        ])->all();
    }

    private function fillFromShift(Shift $shift): void
    {
        $shift->loadMissing('breaks');
        $this->start_time = $this->clock($shift->start_time);
        $this->end_time = $this->clock($shift->end_time);
        $this->late_from = $this->clock($shift->late_from);
        $this->morning_cutoff = $this->clock($shift->morning_cutoff);
        $this->half_day_from = $this->clock($shift->half_day_from);
        $this->early_leave_from = $this->clock($shift->early_leave_from);
        $this->breaks = $shift->breaks->map(fn ($break) => [
            'name' => $break->name,
            'start_time' => $this->clock($break->start_time),
            'end_time' => $this->clock($break->end_time),
            'is_paid' => (bool) $break->is_paid,
        ])->all();
    }

    private function clearTimes(): void
    {
        $this->start_time = '';
        $this->end_time = '';
        $this->late_from = '';
        $this->morning_cutoff = '';
        $this->half_day_from = '';
        $this->early_leave_from = '';
        $this->breaks = [];
    }

    /** @return array<string, string> */
    private function timePayload(): array
    {
        return [
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'late_from' => $this->late_from,
            'morning_cutoff' => $this->morning_cutoff,
            'half_day_from' => $this->half_day_from,
            'early_leave_from' => $this->early_leave_from,
        ];
    }

    /** @return array<string, mixed> */
    private function timeRules(): array
    {
        return [
            'start_time' => ['required'],
            'end_time' => ['required'],
            'late_from' => ['required'],
            'morning_cutoff' => ['required'],
            'half_day_from' => ['required'],
            'early_leave_from' => ['required'],
            'breaks' => ['array'],
            'breaks.*.name' => ['required', 'string', 'max:50'],
            'breaks.*.start_time' => ['required'],
            'breaks.*.end_time' => ['required'],
            'breaks.*.is_paid' => ['boolean'],
        ];
    }

    private function clock(mixed $value): string
    {
        return substr((string) $value, 0, 5);
    }
}
