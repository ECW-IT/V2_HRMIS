<?php

namespace Modules\LegacyImport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\LegacyImport\Database\Factories\StgEmployeeFactory;

class StgEmployee extends Model
{
    /** @use HasFactory<StgEmployeeFactory> */
    use HasFactory;

    protected $fillable = [
        'import_run_id',
        'legacy_hrm_id',
        'legacy_branch_id',
        'raw',
        'normalised_employee_no',
        'normalised_nic',
        'company_code',
        'loaded_employment_id',
        'load_error',
    ];

    protected function casts(): array
    {
        return [
            'raw' => 'array',
            'legacy_hrm_id' => 'integer',
            'legacy_branch_id' => 'integer',
            'loaded_employment_id' => 'integer',
        ];
    }

    public function importRun(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class);
    }

    protected static function newFactory(): StgEmployeeFactory
    {
        return StgEmployeeFactory::new();
    }
}
