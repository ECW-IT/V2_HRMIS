<?php

namespace Modules\Attendance\Models;

use App\Support\Company\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Attendance\Database\Factories\TimecardFactory;
use Modules\Attendance\Enums\DayType;
use Modules\Attendance\Enums\TimecardException;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;

class Timecard extends Model
{
    /** @use HasFactory<TimecardFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'employment_id',
        'work_date',
        'pay_period_id',
        'shift_id',
        'first_in',
        'last_out',
        'punch_count',
        'day_type',
        'day_value',
        'short_leave',
        'late_in_minutes',
        'early_out_minutes',
        'ot_morning_minutes',
        'ot_evening_minutes',
        'is_holiday',
        'exception',
        'computed_at',
        'late_waived',
        'ot_approved_minutes',
        'ot_decided_by',
        'ot_decided_at',
        'leave_type_id',
        'leave_days',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'first_in' => 'datetime',
            'last_out' => 'datetime',
            'punch_count' => 'integer',
            'day_type' => DayType::class,
            'day_value' => 'decimal:2',
            'short_leave' => 'boolean',
            'late_in_minutes' => 'integer',
            'early_out_minutes' => 'integer',
            'ot_morning_minutes' => 'integer',
            'ot_evening_minutes' => 'integer',
            'is_holiday' => 'boolean',
            'exception' => TimecardException::class,
            'computed_at' => 'datetime',
            'late_waived' => 'boolean',
            'ot_approved_minutes' => 'integer',
            'ot_decided_at' => 'datetime',
            'leave_days' => 'decimal:2',
        ];
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function payPeriod(): BelongsTo
    {
        return $this->belongsTo(PayPeriod::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    protected static function newFactory(): TimecardFactory
    {
        return TimecardFactory::new();
    }
}
