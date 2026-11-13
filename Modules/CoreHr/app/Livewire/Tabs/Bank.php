<?php

namespace Modules\CoreHr\Livewire\Tabs;

use App\Exceptions\Domain\DomainException;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Livewire\Concerns\LoadsBankOptions;
use Modules\CoreHr\Models\EmployeeBankAccount;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Services\BankAccountService;

class Bank extends Component
{
    use LoadsBankOptions;

    public Employment $employment;

    public bool $showForm = false;

    public string $effectiveFrom = '';

    public bool $isCash = false;

    public string|int|null $bankId = null;

    public string|int|null $bankBranchId = null;

    public string $accountNo = '';

    public string $accountName = '';

    public string $message = '';

    public string $errorMessage = '';

    public function mount(Employment $employment): void
    {
        $this->employment = $employment;
    }

    public function openForm(): void
    {
        $this->authorize('employee.bank.manage');

        $this->reset(['isCash', 'bankId', 'bankBranchId', 'accountNo', 'message', 'errorMessage']);
        $this->resetErrorBag();
        $this->effectiveFrom = max(
            CarbonImmutable::now(config('app.timezone'))->toDateString(),
            $this->employment->hire_date->toDateString(),
        );
        $this->accountName = $this->employment->person->name_with_initials;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
    }

    public function save(BankAccountService $accounts): void
    {
        $this->authorize('employee.bank.manage');

        $this->message = '';
        $this->errorMessage = '';

        $rules = [
            'effectiveFrom' => ['required', 'date_format:Y-m-d'],
            'isCash' => ['boolean'],
        ];

        if (! $this->isCash) {
            $rules += [
                'bankId' => ['required', 'integer'],
                'bankBranchId' => ['required', 'integer'],
                'accountNo' => ['required', 'string', 'max:30'],
                'accountName' => ['required', 'string', 'max:150'],
            ];
        }

        $this->validate($rules);

        try {
            $accounts->set($this->employment, [
                'effective_from' => $this->effectiveFrom,
                'is_cash' => $this->isCash,
                'bank_id' => $this->isCash ? null : (int) $this->bankId,
                'bank_branch_id' => $this->isCash ? null : (int) $this->bankBranchId,
                'account_no' => $this->isCash ? null : $this->accountNo,
                'account_name' => $this->isCash ? null : $this->accountName,
            ]);
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->showForm = false;
        $this->message = 'Saved.';
        $this->dispatch('employment-updated');
    }

    public function render(): View
    {
        $this->authorize('employee.bank.view');

        $history = EmployeeBankAccount::query()
            ->with(['bank', 'branch'])
            ->where('employment_id', $this->employment->id)
            ->orderByDesc('effective_from')
            ->get();

        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();
        $current = $history->first(fn (EmployeeBankAccount $row) => $row->effective_from->toDateString() <= $today
            && ($row->effective_to === null || $row->effective_to->toDateString() >= $today));

        return view('corehr::livewire.tabs.bank', [
            ...$this->bankOptions(),
            'history' => $history,
            'current' => $current,
        ]);
    }
}
