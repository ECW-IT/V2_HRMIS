<?php

namespace Modules\CoreHr\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CoreHr\Database\Factories\BankBranchFactory;

class BankBranch extends Model
{
    /** @use HasFactory<BankBranchFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'bank_id',
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    protected static function newFactory(): BankBranchFactory
    {
        return BankBranchFactory::new();
    }
}
