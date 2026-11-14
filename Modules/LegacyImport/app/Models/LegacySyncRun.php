<?php

namespace Modules\LegacyImport\Models;

use App\Models\User;
use App\Support\Company\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\LegacyImport\Database\Factories\LegacySyncRunFactory;

class LegacySyncRun extends Model
{
    /** @use HasFactory<LegacySyncRunFactory> */
    use BelongsToCompany, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'from_at',
        'to_at',
        'file_path',
        'new_hires',
        'exits',
        'changes',
        'applied_by',
        'applied_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_at' => 'datetime',
            'to_at' => 'datetime',
            'applied_at' => 'datetime',
            'created_at' => 'datetime',
            'new_hires' => 'integer',
            'exits' => 'integer',
            'changes' => 'integer',
        ];
    }

    public function appliedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    protected static function newFactory(): LegacySyncRunFactory
    {
        return LegacySyncRunFactory::new();
    }
}
