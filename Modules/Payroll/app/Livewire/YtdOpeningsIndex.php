<?php

namespace Modules\Payroll\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Support\Company\CompanyContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Models\YtdOpening;
use Modules\Payroll\Services\YtdService;
use Modules\Payroll\Support\PayrollNav;

class YtdOpeningsIndex extends Component
{
    public bool $showForm = false;

    public string $employmentId = '';

    public string $tax_year_start = '';

    public string $months_covered = '0';

    public string $gross = '0.00';

    public string $taxable_pay = '0.00';

    public string $tax = '0.00';

    public string $epf_employee = '0.00';

    public string $epf_employer = '0.00';

    public string $etf = '0.00';

    public string $note = '';

    public ?string $formError = null;

    public function boot(): void
    {
        CompanyContext::ensureSoleSelected();
    }

    public function openCreate(): void
    {
        $this->authorize('payroll.config');
        $this->formError = null;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->formError = null;
    }

    public function save(YtdService $ytd): void
    {
        $this->authorize('payroll.config');
        $this->formError = null;

        $this->validate([
            'employmentId' => ['required', 'integer'],
            'tax_year_start' => ['required', 'date'],
            'months_covered' => ['required', 'integer', 'min:0', 'max:11'],
            'gross' => ['required', 'string'],
            'taxable_pay' => ['required', 'string'],
            'tax' => ['required', 'string'],
            'epf_employee' => ['required', 'string'],
            'epf_employer' => ['required', 'string'],
            'etf' => ['required', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        try {
            $ytd->setOpening(Employment::query()->findOrFail((int) $this->employmentId), $this->tax_year_start, [
                'months_covered' => (int) $this->months_covered,
                'gross' => $this->gross,
                'taxable_pay' => $this->taxable_pay,
                'tax' => $this->tax,
                'epf_employee' => $this->epf_employee,
                'epf_employer' => $this->epf_employer,
                'etf' => $this->etf,
                'note' => $this->note !== '' ? $this->note : null,
            ], auth()->user());
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        } catch (AuthorizationException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->closeForm();
    }

    public function render(): View
    {
        $this->authorize('payroll.config');

        return view('payroll::livewire.ytd-openings-index', [
            'openings' => YtdOpening::query()->with('employment.person')->orderByDesc('tax_year_start')->get(),
            'employments' => Employment::query()->with('person')->orderBy('employee_no')->get(),
        ])->layout('components.layouts.module', [
            'moduleName' => 'Payroll',
            'title' => 'Year-to-date openings',
            'moduleNav' => PayrollNav::items('ytd-openings'),
        ]);
    }
}
