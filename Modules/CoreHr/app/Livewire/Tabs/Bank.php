<?php

namespace Modules\CoreHr\Livewire\Tabs;

use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Livewire\Concerns\LoadsBankOptions;
use Modules\CoreHr\Livewire\Concerns\RejectsResignedMutations;
use Modules\CoreHr\Livewire\Concerns\SubmitsEmploymentRequests;
use Modules\CoreHr\Models\EmployeeBankAccount;
use Modules\CoreHr\Models\Employment;

class Bank extends Component
{
    use LoadsBankOptions;
    use RejectsResignedMutations;
    use SubmitsEmploymentRequests;

    public Employment $employment;

    public bool $showForm = false;

    public string $effectiveFrom = '';

    public bool $isCash = false;

    public string|int|null $bankId = null;

    public string|int|null $bankBranchId = null;

    public string $accountNo = '';

    public string $accountName = '';

    public function mount(Employment $employment): void
    {
        $this->employment = $employment;
        $this->flashOnParent = false;
    }

    public function openForm(): void
    {
        $this->authorize('employee.bank.manage');
        $this->ensureNotResigned();

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

    public function save(): void
    {
        $this->authorize('employee.bank.manage');
        $this->ensureNotResigned();

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

        $saved = $this->submitRequest('SET_BANK', [
            'account' => [
                'effective_from' => $this->effectiveFrom,
                'is_cash' => $this->isCash,
                'bank_id' => $this->isCash ? null : (int) $this->bankId,
                'bank_branch_id' => $this->isCash ? null : (int) $this->bankBranchId,
                'account_no' => $this->isCash ? null : $this->accountNo,
                'account_name' => $this->isCash ? null : $this->accountName,
            ],
        ]);

        if ($saved) {
            $this->showForm = false;
        }
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
