<?php

namespace Modules\Attendance\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Attendance\Database\Factories\EmploymentTimeProfileBreakFactory;

class EmploymentTimeProfileBreak extends Model
{
    /** @use HasFactory<EmploymentTimeProfileBreakFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'employment_time_profile_id',
        'name',
        'start_time',
        'end_time',
        'is_paid',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(EmploymentTimeProfile::class, 'employment_time_profile_id');
    }

    protected static function newFactory(): EmploymentTimeProfileBreakFactory
    {
        return EmploymentTimeProfileBreakFactory::new();
    }
}
