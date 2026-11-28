<?php

namespace Modules\Payroll\Models;

use App\Support\Audit\Auditable;
use App\Support\Effective\EffectiveDated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Payroll\Database\Factories\CategoryPayRuleFactory;
use Modules\Payroll\Enums\ExtraDayMethod;

class CategoryPayRule extends Model
{
    /** @use HasFactory<CategoryPayRuleFactory> */
    use Auditable, EffectiveDated, HasFactory;

    protected $table = 'category_pay_rules';

    protected string $effectiveOwnerKey = 'employee_category_id';

    protected $fillable = [
        'employee_category_id',
        'extra_day_method',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'extra_day_method' => ExtraDayMethod::class,
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function employeeCategory(): BelongsTo
    {
        return $this->belongsTo(EmployeeCategory::class);
    }

    protected static function newFactory(): CategoryPayRuleFactory
    {
        return CategoryPayRuleFactory::new();
    }
}
