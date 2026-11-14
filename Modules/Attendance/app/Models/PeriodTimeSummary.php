<?php

namespace Modules\Attendance\Models;

use App\Support\Company\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Attendance\Database\Factories\PeriodTimeSummaryFactory;
use Modules\Attendance\Enums\AttendanceMode;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Grade;

class PeriodTimeSummary extends Model
{
    /** @use HasFactory<PeriodTimeSummaryFactory> */
    use BelongsToCompany, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'pay_period_id',
        'employment_id',
        'version',
        'grade_id',
        'attendance_mode',
        'employed_days',
        'expected_days',
        'worked_days',
        'full_days',
        'half_days',
        'short_days',
        'short_leave_count',
        'paid_leave_days',
        'unpaid_leave_days',
        'credited_days',
        'extra_days',
        'nopay_days',
        'late_minutes_total',
        'late_minutes_chargeable',
        'ot_normal_minutes',
        'ot_double_minutes',
        'has_override',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'attendance_mode' => AttendanceMode::class,
            'employed_days' => 'integer',
            'expected_days' => 'decimal:1',
            'worked_days' => 'decimal:2',
            'full_days' => 'integer',
            'half_days' => 'integer',
            'short_days' => 'integer',
            'short_leave_count' => 'integer',
            'paid_leave_days' => 'decimal:1',
            'unpaid_leave_days' => 'decimal:1',
            'credited_days' => 'decimal:2',
            'extra_days' => 'decimal:2',
            'nopay_days' => 'decimal:2',
            'late_minutes_total' => 'integer',
            'late_minutes_chargeable' => 'integer',
            'ot_normal_minutes' => 'integer',
            'ot_double_minutes' => 'integer',
            'has_override' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function payPeriod(): BelongsTo
    {
        return $this->belongsTo(PayPeriod::class);
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Period time summaries cannot be updated.');
    }

    public function delete(): ?bool
    {
        throw new \LogicException('Period time summaries cannot be deleted.');
    }

    protected static function newFactory(): PeriodTimeSummaryFactory
    {
        return PeriodTimeSummaryFactory::new();
    }
}
