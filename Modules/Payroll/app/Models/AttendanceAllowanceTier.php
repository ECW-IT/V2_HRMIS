<?php

namespace Modules\Payroll\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Database\Factories\AttendanceAllowanceTierFactory;

class AttendanceAllowanceTier extends Model
{
    /** @use HasFactory<AttendanceAllowanceTierFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'base_amount',
        'per_day_rate',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'per_day_rate' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    protected static function newFactory(): AttendanceAllowanceTierFactory
    {
        return AttendanceAllowanceTierFactory::new();
    }
}
