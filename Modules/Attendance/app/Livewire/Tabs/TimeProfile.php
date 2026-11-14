<?php

namespace Modules\Attendance\Livewire\Tabs;

use Illuminate\View\View;
use Livewire\Component;
use Modules\Attendance\Enums\AttendanceMode;
use Modules\Attendance\Enums\OtMode;
use Modules\Attendance\Exceptions\InvalidTimeProfile;
use Modules\Attendance\Models\EmploymentTimeProfile;
use Modules\Attendance\Models\Shift;
use Modules\Attendance\Services\TimeProfileService;
use Modules\CoreHr\Models\Employment;

class TimeProfile extends Component
{
    public Employment $employment;

    public bool $showForm = false;

    public string $effective_from = '';

    public string|int|null $shift_id = null;

    public string $attendance_mode = 'PUNCH';

    public string $ot_mode = 'NONE';

    public bool $late_deduction = false;

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
        $current = app(TimeProfileService::class)->on($this->employment, now(config('app.timezone'))->toDateString());
        $this->shift_id = $current?->shift_id;
        $this->attendance_mode = $current?->attendance_mode->value ?? AttendanceMode::PUNCH->value;
        $this->ot_mode = $current?->ot_mode->value ?? OtMode::NONE->value;
        $this->late_deduction = (bool) ($current?->late_deduction ?? false);
        $this->effective_from = now(config('app.timezone'))->toDateString();
        $this->error = '';
        $this->showForm = true;
    }

    public function save(TimeProfileService $service): void
    {
        $this->authorize('employee.update');
        $this->error = '';
        $this->message = '';

        $validated = $this->validate([
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'attendance_mode' => ['required'],
            'ot_mode' => ['required'],
            'late_deduction' => ['boolean'],
        ]);

        try {
            $service->set($this->employment, [
                'effective_from' => $validated['effective_from'],
                'shift_id' => (int) $validated['shift_id'],
                'attendance_mode' => $validated['attendance_mode'],
                'ot_mode' => $validated['ot_mode'],
                'late_deduction' => $validated['late_deduction'],
            ]);
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

        return view('attendance::livewire.tabs.time-profile', [
            'current' => $current,
            'history' => $history,
            'shifts' => Shift::query()->where('is_active', true)->orderBy('code')->get(),
            'attendanceModes' => AttendanceMode::cases(),
            'otModes' => OtMode::cases(),
        ]);
    }
}
