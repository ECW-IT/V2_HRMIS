<?php

namespace Modules\Organisation\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Database\Factories\StaffWorkCategoryFactory;

class StaffWorkCategory extends Model
{
    /** @use HasFactory<StaffWorkCategoryFactory> */
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

    public function employments(): HasMany
    {
        return $this->hasMany(Employment::class);
    }

    protected static function newFactory(): StaffWorkCategoryFactory
    {
        return StaffWorkCategoryFactory::new();
    }
}
