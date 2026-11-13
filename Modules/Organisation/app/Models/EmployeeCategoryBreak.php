<?php

namespace Modules\Organisation\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Organisation\Database\Factories\EmployeeCategoryBreakFactory;

class EmployeeCategoryBreak extends Model
{
    /** @use HasFactory<EmployeeCategoryBreakFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'employee_category_id',
        'break_type_id',
        'start_time',
        'end_time',
        'duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EmployeeCategory::class, 'employee_category_id');
    }

    public function breakType(): BelongsTo
    {
        return $this->belongsTo(BreakType::class);
    }

    protected static function newFactory(): EmployeeCategoryBreakFactory
    {
        return EmployeeCategoryBreakFactory::new();
    }
}
