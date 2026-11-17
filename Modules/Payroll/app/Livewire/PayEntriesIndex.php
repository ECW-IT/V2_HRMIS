<?php

namespace Modules\Payroll\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Support\Company\CompanyContext;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Enums\PayEntryStatus;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayEntry;
use Modules\Payroll\Services\PayEntryService;
use Modules\Payroll\Support\PayrollNav;

class PayEntriesIndex extends Component
{
    public bool $showForm = false;

    public string $payPeriodId = '';

    public string $employmentId = '';

    public string $component_code = '';

    public string $quantity = '';

    public string $amount = '';

    public string $reference = '';

    public string $note = '';

    public ?string $formError = null;

    public function boot(): void
    {
        CompanyContext::ensureSoleSelected();
    }

    public function openCreate(): void
    {
        $this->authorize('pay-entry.manage');
        $this->formError = null;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->formError = null;
    }

    public function save(PayEntryService $entries): void
    {
        $this->authorize('pay-entry.manage');
        $this->formError = null;

        $this->validate([
            'payPeriodId' => ['required', 'integer'],
            'employmentId' => ['required', 'integer'],
            'component_code' => ['required', 'string'],
            'quantity' => ['nullable', 'string'],
            'amount' => ['nullable', 'string'],
            'reference' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        try {
            $entries->add(
                Employment::query()->findOrFail((int) $this->employmentId),
                PayPeriod::query()->findOrFail((int) $this->payPeriodId),
                [
                    'component_code' => $this->component_code,
                    'quantity' => $this->quantity !== '' ? $this->quantity : null,
                    'amount' => $this->amount !== '' ? $this->amount : null,
                    'reference' => $this->reference !== '' ? $this->reference : null,
                    'note' => $this->note !== '' ? $this->note : null,
                ],
                auth()->user(),
            );
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->closeForm();
    }

    public function cancel(int $entryId, PayEntryService $entries): void
    {
        $this->authorize('pay-entry.manage');
        $this->formError = null;

        try {
            $entries->cancel(PayEntry::query()->findOrFail($entryId), auth()->user());
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();
        }
    }

    public function render(): View
    {
        $this->authorize('pay-entry.manage');

        $rows = PayEntry::query()->with(['employment.person', 'payComponent'])->orderByDesc('id');
        if ($this->payPeriodId !== '') {
            $rows->where('pay_period_id', (int) $this->payPeriodId);
        }

        return view('payroll::livewire.pay-entries-index', [
            'entries' => $rows->get(),
            'employments' => Employment::query()->with('person')->orderBy('employee_no')->get(),
            'periods' => PayPeriod::query()->orderByDesc('start_date')->get(),
            'components' => PayComponent::query()->where('is_active', true)->orderBy('code')->get(),
            'active' => PayEntryStatus::ACTIVE,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Payroll',
            'title' => 'Pay entries',
            'moduleNav' => PayrollNav::items('pay-entries'),
        ]);
    }
}
