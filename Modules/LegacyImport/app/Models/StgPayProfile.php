<?php

namespace Modules\LegacyImport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CoreHr\Models\Employment;
use Modules\LegacyImport\Database\Factories\StgPayProfileFactory;
use Modules\Organisation\Models\Company;

class StgPayProfile extends Model
{
    /** @use HasFactory<StgPayProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'import_run_id',
        'legacy_profile_id',
        'emp_no',
        'company_code',
        'company_id',
        'employment_id',
        'basic_salary',
        'ot_rate_mode',
        'attendance_type',
        'hostal',
        'branch_id',
        'status',
        'raw',
        'blocked',
        'loaded',
        'load_error',
    ];

    protected function casts(): array
    {
        return [
            'raw' => 'array',
            'basic_salary' => 'decimal:2',
            'hostal' => 'integer',
            'branch_id' => 'integer',
            'status' => 'integer',
            'legacy_profile_id' => 'integer',
            'blocked' => 'boolean',
            'loaded' => 'boolean',
        ];
    }

    public function importRun(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(StgPayComponent::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(StgLoan::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(StgAdvance::class);
    }

    protected static function newFactory(): StgPayProfileFactory
    {
        return StgPayProfileFactory::new();
    }
}
