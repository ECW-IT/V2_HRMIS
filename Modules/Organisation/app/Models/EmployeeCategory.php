<?php

namespace Modules\Organisation\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Organisation\Database\Factories\EmployeeCategoryFactory;

class EmployeeCategory extends Model
{
    /** @use HasFactory<EmployeeCategoryFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'name_key',
        'is_active',
        'annual_leaves',
        'casual_leaves',
        'medical_leaves',
        'normal_ot_rate',
        'double_ot_rate',
        'no_pay_rate_per_hour',
        'no_pay_rate_per_day',
        'saturday_rate',
        'sunday_rate',
        'payroll_workdays',
        'payroll_workhours',
        'saturday_ot_mode',
        'custom_saturday_ot_type',
        'sunday_ot_mode',
        'custom_sunday_ot_type',
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
            'is_active' => 'boolean',
            'annual_leaves' => 'integer',
            'casual_leaves' => 'integer',
            'medical_leaves' => 'integer',
            'normal_ot_rate' => 'decimal:2',
            'double_ot_rate' => 'decimal:2',
            'no_pay_rate_per_hour' => 'decimal:2',
            'no_pay_rate_per_day' => 'decimal:2',
            'saturday_rate' => 'decimal:2',
            'sunday_rate' => 'decimal:2',
            'payroll_workdays' => 'integer',
            'payroll_workhours' => 'integer',
            'saturday_ot_mode' => 'integer',
            'custom_saturday_ot_type' => 'decimal:2',
            'sunday_ot_mode' => 'integer',
            'custom_sunday_ot_type' => 'decimal:2',
        ];
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(EmployeeCategoryBreak::class);
    }

    public function dutySchedules(): HasMany
    {
        return $this->hasMany(EmployeeCategoryDutySchedule::class);
    }

    protected static function newFactory(): EmployeeCategoryFactory
    {
        return EmployeeCategoryFactory::new();
    }
}
