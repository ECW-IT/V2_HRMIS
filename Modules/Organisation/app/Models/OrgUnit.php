<?php

namespace Modules\Organisation\Models;

use App\Support\Audit\Auditable;
use App\Support\Company\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
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

    /** Sections directly under this department. */
    public function sections(): HasMany
    {
        return $this->children()->where('type', OrgUnitType::SECTION);
    }

    /** Sub sections directly under this section. */
    public function subSections(): HasMany
    {
        return $this->children()->where('type', OrgUnitType::SUBSECTION);
    }

    /**
     * Owning department for this unit.
     * Returns this model when it is already a department; otherwise walks parents.
     */
    public function department(): ?self
    {
        if ($this->type === OrgUnitType::DEPARTMENT) {
            return $this;
        }

        $current = $this->parent;

        while ($current !== null) {
            if ($current->type === OrgUnitType::DEPARTMENT) {
                return $current;
            }

            $current = $current->parent;
        }

        return null;
    }

    /** @param  Builder<self>  $query */
    public function scopeDepartments(Builder $query): Builder
    {
        return $query->where('type', OrgUnitType::DEPARTMENT);
    }

    /** @param  Builder<self>  $query */
    public function scopeSections(Builder $query): Builder
    {
        return $query->where('type', OrgUnitType::SECTION);
    }

    /** @param  Builder<self>  $query */
    public function scopeSubSections(Builder $query): Builder
    {
        return $query->where('type', OrgUnitType::SUBSECTION);
    }

    protected static function newFactory(): OrgUnitFactory
    {
        return OrgUnitFactory::new();
    }
}
