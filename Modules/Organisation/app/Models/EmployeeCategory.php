<?php

namespace Modules\Organisation\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Organisation\Database\Factories\EmployeeCategoryFactory;

class EmployeeCategory extends Model
{
    /** @use HasFactory<EmployeeCategoryFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'name_key',
        'level',
        'is_active',
        'early_ot',
        'evening_ot',
        'late_deduction',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_active' => 'boolean',
            'early_ot' => 'boolean',
            'evening_ot' => 'boolean',
            'late_deduction' => 'boolean',
        ];
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(EmployeeCategoryBreak::class);
    }

    public function timeRuleSummary(): string
    {
        $breaks = $this->relationLoaded('breaks') ? $this->breaks : $this->breaks()->get();
        $names = $breaks->pluck('name')->filter(fn ($name) => trim((string) $name) !== '')->values();
        $breakText = $names->isEmpty() ? 'none' : $names->join(', ', ' and ');

        return sprintf(
            'Category: early OT %s, evening OT %s, late %s, breaks %s',
            $this->early_ot ? 'on' : 'off',
            $this->evening_ot ? 'on' : 'off',
            $this->late_deduction ? 'on' : 'off',
            $breakText,
        );
    }

    protected static function newFactory(): EmployeeCategoryFactory
    {
        return EmployeeCategoryFactory::new();
    }
}
