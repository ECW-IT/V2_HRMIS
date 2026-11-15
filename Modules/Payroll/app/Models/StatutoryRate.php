<?php

namespace Modules\Payroll\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Database\Factories\StatutoryRateFactory;

class StatutoryRate extends Model
{
    /** @use HasFactory<StatutoryRateFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'percent',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'percent' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    protected static function newFactory(): StatutoryRateFactory
    {
        return StatutoryRateFactory::new();
    }
}
