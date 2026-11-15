<?php

namespace Modules\Payroll\Models;

use App\Support\Audit\Auditable;
use App\Support\Company\BelongsToCompany;
use App\Support\Effective\EffectiveDated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Database\Factories\EmployeeCompensationFactory;
use Modules\Payroll\Enums\OtRateMode;
use Modules\Workflow\Models\ApprovalRequest;

class EmployeeCompensation extends Model
{
    /** @use HasFactory<EmployeeCompensationFactory> */
    use Auditable, BelongsToCompany, EffectiveDated, HasFactory;

    protected $table = 'employee_compensations';

    protected string $effectiveOwnerKey = 'employment_id';

    /** @var list<string> */
    protected array $auditMasked = ['basic_salary', 'ot_rate'];

    protected $fillable = [
        'company_id',
        'employment_id',
        'effective_from',
        'effective_to',
        'basic_salary',
        'ot_rate_mode',
        'ot_rate',
        'attendance_tier_code',
        'change_reason',
        'approval_request_id',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'basic_salary' => 'decimal:2',
            'ot_rate_mode' => OtRateMode::class,
            'ot_rate' => 'decimal:2',
        ];
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    protected static function newFactory(): EmployeeCompensationFactory
    {
        return EmployeeCompensationFactory::new();
    }
}
