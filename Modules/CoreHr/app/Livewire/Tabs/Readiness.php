<?php

namespace Modules\CoreHr\Livewire\Tabs;

use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Readiness\ReadinessService;

class Readiness extends Component
{
    public Employment $employment;

    public string $asOf = '';

    public function mount(Employment $employment): void
    {
        $this->employment = $employment;
        $this->asOf = CarbonImmutable::now(config('app.timezone'))->toDateString();
    }

    public function render(ReadinessService $readiness): View
    {
        $this->authorize('employee.view');

        $this->validate(['asOf' => ['required', 'date_format:Y-m-d']]);

        return view('corehr::livewire.tabs.readiness', [
            'issues' => $readiness->forEmployment($this->employment, $this->asOf),
        ]);
    }
}
