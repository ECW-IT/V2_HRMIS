<?php

namespace Modules\Payroll\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Database\Factories\PayComponentFactory;
use Modules\Payroll\Enums\CalcMethod;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Enums\FundCode;

class PayComponent extends Model
{
    /** @use HasFactory<PayComponentFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'kind',
        'method',
        'sheet',
        'epf_liable',
        'taxable',
        'fund',
        'night_base_hours',
        'is_system',
        'is_active',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ComponentKind::class,
            'method' => CalcMethod::class,
            'fund' => FundCode::class,
            'sheet' => 'integer',
            'epf_liable' => 'boolean',
            'taxable' => 'boolean',
            'night_base_hours' => 'integer',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    protected static function newFactory(): PayComponentFactory
    {
        return PayComponentFactory::new();
    }
}
