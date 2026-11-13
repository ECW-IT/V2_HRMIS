<?php

namespace Modules\LegacyImport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\LegacyImport\Database\Factories\StgIssueFactory;

class StgIssue extends Model
{
    /** @use HasFactory<StgIssueFactory> */
    use HasFactory;

    protected $fillable = [
        'import_run_id',
        'legacy_hrm_id',
        'code',
        'severity',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'legacy_hrm_id' => 'integer',
        ];
    }

    public function importRun(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class);
    }

    protected static function newFactory(): StgIssueFactory
    {
        return StgIssueFactory::new();
    }
}
