<?php

namespace Modules\CoreHr\Livewire\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Livewire\Concerns\SubmitsEmploymentRequests;
use Modules\CoreHr\Models\Employment;

class ExitEmployment extends Component
{
    use SubmitsEmploymentRequests;

    public Employment $employment;

    public string $lastWorkingDay = '';

    public string $reason = '';

    public string $remarks = '';

    public function mount(Employment $employment): void
    {
        $this->employment = $employment;
        $this->lastWorkingDay = max(
            CarbonImmutable::now(config('app.timezone'))->toDateString(),
            $employment->hire_date->toDateString(),
        );
    }

    /** @return list<ExitReason> */
    protected function reasons(): array
    {
        return array_values(array_filter(
            ExitReason::cases(),
            fn (ExitReason $reason) => $reason !== ExitReason::SISTER_COMPANY_TRANSFER,
        ));
    }

    public function save(): void
    {
        $this->authorize('employee.exit');

        $this->validate([
            'lastWorkingDay' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', Rule::in(array_map(fn (ExitReason $r) => $r->value, $this->reasons()))],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->submitRequest('EXIT', [
            'on' => $this->lastWorkingDay,
            'reason' => $this->reason,
            'remarks' => $this->remarks !== '' ? $this->remarks : null,
        ]);
    }

    public function render(): View
    {
        return view('corehr::livewire.actions.exit-employment', [
            'reasons' => $this->reasons(),
        ]);
    }
}
