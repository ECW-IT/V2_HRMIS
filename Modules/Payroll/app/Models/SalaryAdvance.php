<?php

namespace Modules\Payroll\Models;

use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Company\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Database\Factories\SalaryAdvanceFactory;
use Modules\Payroll\Enums\AdvanceStatus;
use Modules\Workflow\Models\ApprovalRequest;

class SalaryAdvance extends Model
{
    /** @use HasFactory<SalaryAdvanceFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $table = 'salary_advances';

    protected $fillable = [
        'company_id',
        'employment_id',
        'pay_period_id',
        'amount',
        'requested_on',
        'reason',
        'status',
        'approval_request_id',
        'paid_on',
        'paid_by',
        'payment_reference',
        'recovered_pay_run_id',
        'converted_loan_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requested_on' => 'date',
            'status' => AdvanceStatus::class,
            'paid_on' => 'date',
        ];
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function payPeriod(): BelongsTo
    {
        return $this->belongsTo(PayPeriod::class);
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function convertedLoan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'converted_loan_id');
    }

    protected static function newFactory(): SalaryAdvanceFactory
    {
        return SalaryAdvanceFactory::new();
    }
}
