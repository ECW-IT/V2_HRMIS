<?php

namespace Modules\LegacyImport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\LegacyImport\Database\Factories\StgPayComponentFactory;

class StgPayComponent extends Model
{
    /** @use HasFactory<StgPayComponentFactory> */
    use HasFactory;

    protected $fillable = [
        'import_run_id',
        'stg_pay_profile_id',
        'legacy_adju_id',
        'legacy_adjustment_type_id',
        'legacy_name',
        'v2_code',
        'action',
        'amount',
        'skip_zero',
        'blocked',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'skip_zero' => 'boolean',
            'blocked' => 'boolean',
            'legacy_adju_id' => 'integer',
            'legacy_adjustment_type_id' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(StgPayProfile::class, 'stg_pay_profile_id');
    }

    protected static function newFactory(): StgPayComponentFactory
    {
        return StgPayComponentFactory::new();
    }
}
