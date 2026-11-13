<?php

namespace Modules\Attendance\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Attendance\Models\Punch;
use Modules\CoreHr\Models\Employment;

class EmployeePunches extends Component
{
    public int $employmentId;

    public string $month;

    public function mount(int $employmentId, ?string $month = null): void
    {
        $this->employmentId = $employmentId;
        $this->month = $month ?? CarbonImmutable::now(config('app.timezone'))->format('Y-m');
    }

    public function render(): View
    {
        $this->authorize('attendance.punch.view');

        Employment::query()->findOrFail($this->employmentId);

        $start = CarbonImmutable::createFromFormat('Y-m', $this->month, config('app.timezone'))->startOfMonth();
        $end = $start->endOfMonth();

        $punches = Punch::query()
            ->with('device')
            ->where('employment_id', $this->employmentId)
            ->whereBetween('punched_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->orderBy('punched_at')
            ->get();

        return view('attendance::livewire.employee-punches', [
            'punches' => $punches,
        ]);
    }
}
