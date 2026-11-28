<?php

namespace Modules\Leave\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Leave\Database\Factories\LeavePolicyFactory;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\EmployeeCategory;

class LeavePolicy extends Model
{
    /** @use HasFactory<LeavePolicyFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'leave_type_id',
        'company_id',
        'employee_category_id',
        'employment_type',
        'days_per_period',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'days_per_period' => 'decimal:1',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employeeCategory(): BelongsTo
    {
        return $this->belongsTo(EmployeeCategory::class);
    }

    protected static function newFactory(): LeavePolicyFactory
    {
        return LeavePolicyFactory::new();
    }
}
