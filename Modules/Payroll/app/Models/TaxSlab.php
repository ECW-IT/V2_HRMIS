<?php

namespace Modules\Payroll\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Database\Factories\TaxSlabFactory;

class TaxSlab extends Model
{
    /** @use HasFactory<TaxSlabFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'effective_from',
        'effective_to',
        'sequence',
        'width',
        'percent',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'sequence' => 'integer',
            'width' => 'decimal:2',
            'percent' => 'decimal:2',
        ];
    }

    protected static function newFactory(): TaxSlabFactory
    {
        return TaxSlabFactory::new();
    }
}
