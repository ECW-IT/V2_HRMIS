<?php

namespace Modules\Payroll\Models;

use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Company\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Database\Factories\LoanFactory;
use Modules\Payroll\Enums\LoanStatus;
use Modules\Workflow\Models\ApprovalRequest;

class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $table = 'loans';

    protected $fillable = [
        'company_id',
        'employment_id',
        'loan_type_id',
        'loan_no',
        'amount',
        'instalments',
        'first_month',
        'reason',
        'status',
        'approval_request_id',
        'paid_on',
        'payment_reference',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'instalments' => 'integer',
            'first_month' => 'date',
            'status' => LoanStatus::class,
            'paid_on' => 'date',
        ];
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function loanType(): BelongsTo
    {
        return $this->belongsTo(LoanType::class);
    }

    public function instalmentRows(): HasMany
    {
        return $this->hasMany(LoanInstalment::class);
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function newFactory(): LoanFactory
    {
        return LoanFactory::new();
    }
}
