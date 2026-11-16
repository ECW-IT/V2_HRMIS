<?php

namespace Modules\LegacyImport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\LegacyImport\Database\Factories\StgLoanFactory;

class StgLoan extends Model
{
    /** @use HasFactory<StgLoanFactory> */
    use HasFactory;

    protected $fillable = [
        'import_run_id',
        'stg_pay_profile_id',
        'legacy_loan_id',
        'loan_type_id',
        'approved_amount',
        'approved_installments',
        'if_approved',
        'status',
        'paid',
        'unpaid_schedule',
        'outstanding',
        'blocked',
        'loaded',
    ];

    protected function casts(): array
    {
        return [
            'approved_amount' => 'decimal:2',
            'outstanding' => 'decimal:2',
            'unpaid_schedule' => 'array',
            'blocked' => 'boolean',
            'loaded' => 'boolean',
            'legacy_loan_id' => 'integer',
            'loan_type_id' => 'integer',
            'approved_installments' => 'integer',
            'if_approved' => 'integer',
            'status' => 'integer',
            'paid' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(StgPayProfile::class, 'stg_pay_profile_id');
    }

    protected static function newFactory(): StgLoanFactory
    {
        return StgLoanFactory::new();
    }
}
