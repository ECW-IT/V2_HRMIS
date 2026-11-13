<?php

namespace Modules\CoreHr\Livewire\Actions;

use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Livewire\Concerns\SubmitsEmploymentRequests;
use Modules\CoreHr\Models\Employment;

class ConfirmEmployment extends Component
{
    use SubmitsEmploymentRequests;

    public Employment $employment;

    public string $confirmedOn = '';

    public function mount(Employment $employment): void
    {
        $this->employment = $employment;
        $this->confirmedOn = $employment->confirmation_due_date?->toDateString()
            ?? CarbonImmutable::now(config('app.timezone'))->toDateString();
    }

    public function save(): void
    {
        $this->authorize('employee.update');

        $this->validate([
            'confirmedOn' => ['required', 'date_format:Y-m-d'],
        ]);

        $this->submitRequest('CONFIRM', [
            'on' => $this->confirmedOn,
            'reason' => 'Confirmation',
        ]);
    }

    public function render(): View
    {
        return view('corehr::livewire.actions.confirm-employment');
    }
}
