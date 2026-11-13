<?php

namespace Modules\CoreHr\Models;

use App\Support\Audit\Auditable;
use App\Support\Effective\EffectiveDated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CoreHr\Database\Factories\AssignmentFactory;
use Modules\Organisation\Models\Grade;
use Modules\Organisation\Models\Job;
use Modules\Organisation\Models\Location;
use Modules\Organisation\Models\OrgUnit;

class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use Auditable, EffectiveDated, HasFactory;

    protected string $effectiveOwnerKey = 'employment_id';

    protected $fillable = [
        'employment_id',
        'effective_from',
        'effective_to',
        'org_unit_id',
        'job_id',
        'grade_id',
        'location_id',
        'manager_employment_id',
        'change_reason',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employment::class, 'manager_employment_id');
    }

    protected static function newFactory(): AssignmentFactory
    {
        return AssignmentFactory::new();
    }
}
