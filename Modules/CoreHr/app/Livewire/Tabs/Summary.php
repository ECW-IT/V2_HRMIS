<?php

namespace Modules\CoreHr\Livewire\Tabs;

use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Enums\ContactType;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Readiness\ReadinessService;
use Modules\CoreHr\Support\AssignmentLookup;

class Summary extends Component
{
    public Employment $employment;

    public function mount(Employment $employment): void
    {
        $this->employment = $employment;
    }

    public function render(ReadinessService $readiness): View
    {
        $this->authorize('employee.view');

        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();
        $employment = $this->employment->load(['person.contacts', 'employeeCategory']);
        $person = $employment->person;

        $mobile = $person->contacts
            ->where('type', ContactType::MOBILE)
            ->sortByDesc('is_primary')
            ->first();

        return view('corehr::livewire.tabs.summary', [
            'employment' => $employment,
            'person' => $person,
            'assignment' => AssignmentLookup::display($employment),
            'mobile' => $mobile?->value,
            'age' => (int) CarbonImmutable::parse($person->date_of_birth->toDateString())->diffInYears(CarbonImmutable::parse($today)),
            'issues' => $readiness->forEmployment($employment, $today),
            // The company scope hides employments the user may not see.
            'others' => Employment::query()
                ->with('company')
                ->where('person_id', $employment->person_id)
                ->where('id', '!=', $employment->id)
                ->orderByDesc('hire_date')
                ->get(),
        ]);
    }
}
