<?php

namespace Modules\Payroll\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Support\Company\CompanyContext;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Enums\LoanStatus;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\LoanType;
use Modules\Payroll\Services\LoanService;
use Modules\Payroll\Support\PayrollNav;

class LoansIndex extends Component
{
    public bool $showForm = false;

    public string $employmentId = '';

    public string $loan_type_code = '';

    public string $amount = '';

    public string $instalments = '1';

    public string $first_month = '';

    public string $reason = '';

    public string $paidOn = '';

    public string $paymentReference = '';

    public string $graceMonth = '';

    public string $graceReason = '';

    public ?string $formError = null;

    public function boot(): void
    {
        CompanyContext::ensureSoleSelected();
    }

    public function openCreate(): void
    {
        $this->authorize('loan.manage');
        $this->employmentId = '';
        $this->loan_type_code = '';
        $this->amount = '';
        $this->instalments = '1';
        $this->first_month = '';
        $this->reason = '';
        $this->formError = null;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->formError = null;
    }

    public function save(LoanService $loans): void
    {
        $this->authorize('loan.manage');
        $this->formError = null;

        $this->validate([
            'employmentId' => ['required', 'integer'],
            'loan_type_code' => ['required', 'string'],
            'amount' => ['required', 'string'],
            'instalments' => ['required', 'integer', 'min:1', 'max:60'],
            'first_month' => ['required', 'date'],
            'reason' => ['nullable', 'string'],
        ]);

        try {
            $loans->request(Employment::query()->findOrFail((int) $this->employmentId), [
                'loan_type_code' => $this->loan_type_code,
                'amount' => $this->amount,
                'instalments' => (int) $this->instalments,
                'first_month' => $this->first_month,
                'reason' => $this->reason !== '' ? $this->reason : null,
            ], auth()->user());
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->closeForm();
    }

    public function markPaid(int $loanId, LoanService $loans): void
    {
        $this->authorize('advance.pay');
        $this->formError = null;

        try {
            $loans->markPaid(
                Loan::query()->findOrFail($loanId),
                $this->paidOn,
                $this->paymentReference !== '' ? $this->paymentReference : null,
                auth()->user(),
            );
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();
        }
    }

    public function cancel(int $loanId, LoanService $loans): void
    {
        $this->authorize('loan.manage');
        $this->formError = null;

        try {
            $loans->cancel(Loan::query()->findOrFail($loanId), auth()->user());
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();
        }
    }

    public function grace(int $loanId, LoanService $loans): void
    {
        $this->authorize('loan.manage');
        $this->formError = null;

        try {
            $loans->grace(Loan::query()->findOrFail($loanId), $this->graceMonth, $this->graceReason, auth()->user());
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();
        }
    }

    public function render(): View
    {
        $this->authorize('loan.manage');

        return view('payroll::livewire.loans-index', [
            'loans' => Loan::query()->with(['employment.person', 'loanType', 'instalmentRows'])->orderByDesc('id')->get(),
            'employments' => Employment::query()->with('person')->orderBy('employee_no')->get(),
            'loanTypes' => LoanType::query()->where('is_active', true)->orderBy('code')->get(),
            'canPay' => auth()->user()?->can('advance.pay') ?? false,
            'pending' => LoanStatus::PENDING,
            'approved' => LoanStatus::APPROVED,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Payroll',
            'title' => 'Loans',
            'moduleNav' => PayrollNav::items('loans'),
        ]);
    }
}
