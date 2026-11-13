<?php

namespace Modules\CoreHr\Livewire\Actions;

use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Livewire\Concerns\SubmitsEmploymentRequests;
use Modules\CoreHr\Models\Employment;

class Reinstate extends Component
{
    use SubmitsEmploymentRequests;

    public Employment $employment;

    public string $on = '';

    public function mount(Employment $employment): void
    {
        $this->employment = $employment;
        $this->on = CarbonImmutable::now(config('app.timezone'))->toDateString();
    }

    public function save(): void
    {
        $this->authorize('employee.exit');

        $this->validate([
            'on' => ['required', 'date_format:Y-m-d'],
        ]);

        $this->submitRequest('REINSTATE', [
            'on' => $this->on,
            'reason' => 'Reinstatement',
        ]);
    }

    public function render(): View
    {
        return view('corehr::livewire.actions.reinstate');
    }
}
