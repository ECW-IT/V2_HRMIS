<?php

namespace Modules\Organisation\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Organisation\Database\Factories\BreakTypeFactory;

class BreakType extends Model
{
    /** @use HasFactory<BreakTypeFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'is_paid',
        'remarks',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function categoryBreaks(): HasMany
    {
        return $this->hasMany(EmployeeCategoryBreak::class);
    }

    protected static function newFactory(): BreakTypeFactory
    {
        return BreakTypeFactory::new();
    }
}
