<?php

namespace Modules\LegacyImport\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\LegacyImport\Database\Factories\ImportRunFactory;

class ImportRun extends Model
{
    /** @use HasFactory<ImportRunFactory> */
    use HasFactory;

    protected $fillable = [
        'started_at',
        'finished_at',
        'stage',
        'summary',
        'run_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'summary' => 'array',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(StgEmployee::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(StgIssue::class);
    }

    public function runner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_by');
    }

    protected static function newFactory(): ImportRunFactory
    {
        return ImportRunFactory::new();
    }
}
