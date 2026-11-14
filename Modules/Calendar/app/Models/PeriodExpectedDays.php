<?php

namespace Modules\Calendar\Models;

use App\Models\User;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Calendar\Database\Factories\PeriodExpectedDaysFactory;
use Modules\Organisation\Models\Grade;

class PeriodExpectedDays extends Model
{
    /** @use HasFactory<PeriodExpectedDaysFactory> */
    use Auditable, HasFactory;

    protected $table = 'period_expected_days';

    protected $fillable = [
        'pay_period_id',
        'grade_id',
        'days',
        'set_by',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'decimal:1',
        ];
    }

    public function payPeriod(): BelongsTo
    {
        return $this->belongsTo(PayPeriod::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function setByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    protected static function newFactory(): PeriodExpectedDaysFactory
    {
        return PeriodExpectedDaysFactory::new();
    }
}
