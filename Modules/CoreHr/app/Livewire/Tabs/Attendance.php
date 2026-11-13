<?php

namespace Modules\CoreHr\Livewire\Tabs;

use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\Attendance\Models\BiometricEnrolment;
use Modules\CoreHr\Models\Employment;

class Attendance extends Component
{
    public Employment $employment;

    public string $month = '';

    public bool $showEnrol = false;

    public function mount(Employment $employment): void
    {
        $this->employment = $employment;
        $this->month = CarbonImmutable::now(config('app.timezone'))->format('Y-m');
    }

    public function openEnrol(): void
    {
        $this->authorize('attendance.device.manage');
        $this->showEnrol = true;
    }

    #[On('close-enrol')]
    public function closeEnrol(): void
    {
        $this->showEnrol = false;
    }

    #[On('employment-updated')]
    public function afterUpdate(): void
    {
        $this->showEnrol = false;
    }

    public function render(): View
    {
        $this->authorize('attendance.punch.view');

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month) !== 1) {
            $this->month = CarbonImmutable::now(config('app.timezone'))->format('Y-m');
        }

        return view('corehr::livewire.tabs.attendance', [
            'enrolments' => BiometricEnrolment::query()
                ->where('employment_id', $this->employment->id)
                ->orderByDesc('valid_from')
                ->get(),
        ]);
    }
}
