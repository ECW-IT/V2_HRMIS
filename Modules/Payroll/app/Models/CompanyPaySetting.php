<?php

namespace Modules\Payroll\Models;

use App\Support\Audit\Auditable;
use App\Support\Company\BelongsToCompany;
use App\Support\Effective\EffectiveDated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Database\Factories\CompanyPaySettingFactory;

class CompanyPaySetting extends Model
{
    /** @use HasFactory<CompanyPaySettingFactory> */
    use Auditable, BelongsToCompany, EffectiveDated, HasFactory;

    protected string $effectiveOwnerKey = 'company_id';

    protected $fillable = [
        'company_id',
        'late_divisor',
        'attendance_min_offset',
        'attendance_max_offset',
        'hours_per_month',
        'hours_per_day',
        'ot_multiplier',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'late_divisor' => 'integer',
            'attendance_min_offset' => 'decimal:1',
            'attendance_max_offset' => 'decimal:1',
            'hours_per_month' => 'integer',
            'hours_per_day' => 'integer',
            'ot_multiplier' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    protected static function newFactory(): CompanyPaySettingFactory
    {
        return CompanyPaySettingFactory::new();
    }
}
