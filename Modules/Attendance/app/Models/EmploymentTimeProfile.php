<?php

namespace Modules\Attendance\Models;

use App\Support\Audit\Auditable;
use App\Support\Effective\EffectiveDated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Attendance\Casts\NullableBoolean;
use Modules\Attendance\Database\Factories\EmploymentTimeProfileFactory;
use Modules\Attendance\Enums\AttendanceMode;
use Modules\Attendance\Enums\OtMode;
use Modules\CoreHr\Models\Employment;

class EmploymentTimeProfile extends Model
{
    /** @use HasFactory<EmploymentTimeProfileFactory> */
    use Auditable, EffectiveDated, HasFactory;

    protected string $effectiveOwnerKey = 'employment_id';

    protected $fillable = [
        'employment_id',
        'effective_from',
        'effective_to',
        'shift_id',
        'attendance_mode',
        'ot_mode',
        'late_deduction',
        'break_available',
        'uses_custom_times',
        'start_time',
        'end_time',
        'late_from',
        'morning_cutoff',
        'half_day_from',
        'early_leave_from',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'attendance_mode' => AttendanceMode::class,
            'ot_mode' => OtMode::class,
            'late_deduction' => NullableBoolean::class,
            'break_available' => NullableBoolean::class,
            'uses_custom_times' => 'boolean',
        ];
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(EmploymentTimeProfileBreak::class);
    }

    protected static function newFactory(): EmploymentTimeProfileFactory
    {
        return EmploymentTimeProfileFactory::new();
    }
}
