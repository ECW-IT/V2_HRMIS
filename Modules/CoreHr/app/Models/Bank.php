<?php

namespace Modules\CoreHr\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CoreHr\Database\Factories\BankFactory;

class Bank extends Model
{
    /** @use HasFactory<BankFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
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

    public function branches(): HasMany
    {
        return $this->hasMany(BankBranch::class);
    }

    protected static function newFactory(): BankFactory
    {
        return BankFactory::new();
    }
}
