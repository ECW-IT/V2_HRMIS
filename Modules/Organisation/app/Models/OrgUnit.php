<?php

namespace Modules\Organisation\Models;

use App\Support\Audit\Auditable;
use App\Support\Company\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Organisation\Database\Factories\OrgUnitFactory;
use Modules\Organisation\Enums\OrgUnitType;

class OrgUnit extends Model
{
    /** @use HasFactory<OrgUnitFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'parent_id',
        'code',
        'name',
        'type',
        'head_employment_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrgUnitType::class,
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    protected static function newFactory(): OrgUnitFactory
    {
        return OrgUnitFactory::new();
    }
}
