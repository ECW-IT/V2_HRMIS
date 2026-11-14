<?php

namespace Modules\Calendar\Livewire;

use App\Support\Company\CompanyContext;
use Carbon\Carbon;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Services\ExpectedDaysService;
use Modules\Calendar\Services\PayPeriodService;
use Modules\Calendar\Support\CalendarNav;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Grade;

class PayPeriodsIndex extends Component
{
    public function render(PayPeriodService $periods, ExpectedDaysService $expectedDays): View
    {
        $this->authorize('org.view');

        CompanyContext::ensureSoleSelected();
        $companyId = CompanyContext::id();
        $rows = collect();
        $activeGradeCount = Grade::query()->where('is_active', true)->count();

        if ($companyId !== null) {
            $company = Company::query()->findOrFail($companyId);
            $now = Carbon::now(config('app.timezone'));
            // Ensure a sensible window exists for the list.
            $periods->ensureRange(
                $now->copy()->subMonths(2)->startOfMonth(),
                $now->copy()->addMonths(3)->startOfMonth(),
            );

            $rows = PayPeriod::query()
                ->where('company_id', $company->id)
                ->with(['closedBy'])
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->get()
                ->map(function (PayPeriod $period) use ($expectedDays, $activeGradeCount) {
                    $setCount = $activeGradeCount - $expectedDays->missing($period)->count();

                    return [
                        'period' => $period,
                        'set_count' => $setCount,
                        'grade_count' => $activeGradeCount,
                        'missing' => $setCount < $activeGradeCount,
                    ];
                });
        }

        return view('calendar::livewire.pay-periods-index', [
            'companyId' => $companyId,
            'rows' => $rows,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Calendar',
            'title' => 'Pay periods',
            'moduleNav' => CalendarNav::items('periods'),
        ]);
    }
}
