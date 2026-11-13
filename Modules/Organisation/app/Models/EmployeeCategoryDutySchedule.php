<?php

namespace Modules\Organisation\Models;

use App\Support\Audit\Auditable;
use App\Support\Effective\EffectiveDated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Organisation\Database\Factories\EmployeeCategoryDutyScheduleFactory;

class EmployeeCategoryDutySchedule extends Model
{
    /** @use HasFactory<EmployeeCategoryDutyScheduleFactory> */
    use Auditable, EffectiveDated, HasFactory;

    protected string $effectiveOwnerKey = 'employee_category_id';

    protected $fillable = [
        'employee_category_id',
        'effective_from',
        'effective_to',
        'on_duty_time',
        'off_duty_time',
        'morning_short_beginning',
        'morning_short_ending',
        'evening_short_beginning',
        'evening_short_ending',
        'halfday_beginning',
        'late_on',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EmployeeCategory::class, 'employee_category_id');
    }

    protected static function newFactory(): EmployeeCategoryDutyScheduleFactory
    {
        return EmployeeCategoryDutyScheduleFactory::new();
    }
}
