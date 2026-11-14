<?php

namespace Modules\Calendar\Livewire;

use App\Support\Company\CompanyContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Calendar\Enums\PeriodStatus;
use Modules\Calendar\Exceptions\InvalidExpectedDays;
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Models\PeriodExpectedDays;
use Modules\Calendar\Services\ExpectedDaysService;
use Modules\Calendar\Services\PayPeriodService;
use Modules\Calendar\Support\CalendarNav;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Grade;

class ExpectedDaysGrid extends Component
{
    public int|string $monthly_late_grace_minutes = 240;

    public string $message = '';

    public string $error = '';

    /** @var array<string, string|null> keyed "periodId:gradeId" */
    public array $cells = [];

    public function mount(PayPeriodService $periods): void
    {
        $this->authorize('org.view');
        CompanyContext::ensureSoleSelected();
        $this->hydrateFromCompany($periods);
    }

    public function saveCell(int $periodId, int $gradeId, ExpectedDaysService $expectedDays): void
    {
        $this->authorize('period.manage');
        $this->error = '';
        $this->message = '';

        $period = PayPeriod::query()->findOrFail($periodId);
        if ($period->status === PeriodStatus::CLOSED) {
            $this->error = 'The period is closed.';

            return;
        }

        $key = $this->cellKey($periodId, $gradeId);
        $raw = $this->cells[$key] ?? null;
        if ($raw === null || $raw === '') {
            $this->error = 'Enter expected days.';

            return;
        }

        $grade = Grade::query()->findOrFail($gradeId);

        try {
            $expectedDays->set($period, $grade, (float) $raw, auth()->user());
            $this->message = 'Expected days saved.';
            $this->loadCells($period->company_id);
        } catch (InvalidExpectedDays $e) {
            $this->error = $e->getMessage();
        }
    }

    public function copyFromPrevious(int $periodId, ExpectedDaysService $expectedDays): void
    {
        $this->authorize('period.manage');
        $this->error = '';
        $this->message = '';

        $period = PayPeriod::query()->findOrFail($periodId);
        if ($period->status === PeriodStatus::CLOSED) {
            $this->error = 'The period is closed.';

            return;
        }

        $added = $expectedDays->copyFromPrevious($period);
        $this->message = "{$added} value(s) copied.";
        $this->loadCells((int) $period->company_id);
    }

    public function saveLateGrace(): void
    {
        $this->authorize('period.manage');
        $this->error = '';
        $this->message = '';

        $companyId = CompanyContext::id();
        if ($companyId === null) {
            $this->error = 'Select a company first.';

            return;
        }

        $validated = $this->validate([
            'monthly_late_grace_minutes' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);

        $company = Company::query()->findOrFail($companyId);
        $company->update([
            'monthly_late_grace_minutes' => (int) $validated['monthly_late_grace_minutes'],
        ]);
        $this->monthly_late_grace_minutes = (int) $validated['monthly_late_grace_minutes'];
        $this->message = 'Late grace saved.';
    }

    public function render(PayPeriodService $periods): View
    {
        $this->authorize('org.view');

        CompanyContext::ensureSoleSelected();
        $companyId = CompanyContext::id();
        $grades = collect();
        $periodColumns = collect();

        if ($companyId !== null) {
            $company = Company::query()->findOrFail($companyId);
            $periodColumns = $this->ensureColumns($periods, $company);
            $grades = Grade::query()->where('is_active', true)->orderBy('rank')->get();
        }

        return view('calendar::livewire.expected-days-grid', [
            'companyId' => $companyId,
            'grades' => $grades,
            'periodColumns' => $periodColumns,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Calendar',
            'title' => 'Expected working days',
            'moduleNav' => CalendarNav::items('expected-days'),
        ]);
    }

    private function hydrateFromCompany(PayPeriodService $periods): void
    {
        $companyId = CompanyContext::id();
        if ($companyId === null) {
            return;
        }

        $company = Company::query()->findOrFail($companyId);
        $this->monthly_late_grace_minutes = (int) $company->monthly_late_grace_minutes;
        $this->ensureColumns($periods, $company);
        $this->loadCells($companyId);
    }

    /** @return Collection<int, PayPeriod> */
    private function ensureColumns(PayPeriodService $periods, Company $company): Collection
    {
        $now = Carbon::now(config('app.timezone'))->startOfMonth();
        $columns = collect();

        for ($offset = -2; $offset <= 3; $offset++) {
            $month = $now->copy()->addMonths($offset);
            $columns->push($periods->ensure($company, (int) $month->year, (int) $month->month));
        }

        return $columns;
    }

    private function loadCells(int $companyId): void
    {
        $periodIds = PayPeriod::query()
            ->where('company_id', $companyId)
            ->pluck('id');

        $grades = Grade::query()->where('is_active', true)->pluck('id');
        $values = PeriodExpectedDays::query()
            ->whereIn('pay_period_id', $periodIds)
            ->get()
            ->keyBy(fn ($row) => $this->cellKey((int) $row->pay_period_id, (int) $row->grade_id));

        $cells = [];
        foreach ($periodIds as $periodId) {
            foreach ($grades as $gradeId) {
                $key = $this->cellKey((int) $periodId, (int) $gradeId);
                $cells[$key] = isset($values[$key]) ? (string) ((float) $values[$key]->days) : null;
            }
        }

        $this->cells = $cells;
    }

    private function cellKey(int $periodId, int $gradeId): string
    {
        return $periodId.':'.$gradeId;
    }
}
