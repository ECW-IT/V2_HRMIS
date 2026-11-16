<?php

namespace Modules\LegacyImport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\LegacyImport\Database\Factories\StgAdvanceFactory;

class StgAdvance extends Model
{
    /** @use HasFactory<StgAdvanceFactory> */
    use HasFactory;

    protected $fillable = [
        'import_run_id',
        'stg_pay_profile_id',
        'legacy_advance_id',
        'amount',
        'unpaid_amount',
        'is_paid',
        'paid_date',
        'status',
        'converted_to_loan_id',
        'unpaid_deductions',
        'blocked',
        'loaded',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'unpaid_amount' => 'decimal:2',
            'paid_date' => 'date',
            'unpaid_deductions' => 'array',
            'blocked' => 'boolean',
            'loaded' => 'boolean',
            'legacy_advance_id' => 'integer',
            'is_paid' => 'integer',
            'status' => 'integer',
            'converted_to_loan_id' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(StgPayProfile::class, 'stg_pay_profile_id');
    }

    protected static function newFactory(): StgAdvanceFactory
    {
        return StgAdvanceFactory::new();
    }
}
