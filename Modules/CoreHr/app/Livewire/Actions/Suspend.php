<?php

namespace Modules\CoreHr\Livewire\Actions;

use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Livewire\Concerns\SubmitsEmploymentRequests;
use Modules\CoreHr\Models\Employment;

class Suspend extends Component
{
    use SubmitsEmploymentRequests;

    public Employment $employment;

    public string $from = '';

    public string $remarks = '';

    public function mount(Employment $employment): void
    {
        $this->employment = $employment;
        $this->from = CarbonImmutable::now(config('app.timezone'))->toDateString();
    }

    public function save(): void
    {
        $this->authorize('employee.exit');

        $this->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        $this->submitRequest('SUSPEND', [
            'on' => $this->from,
            'remarks' => $this->remarks,
            'reason' => 'Suspension',
        ]);
    }

    public function render(): View
    {
        return view('corehr::livewire.actions.suspend');
    }
}
