<?php

namespace Modules\Attendance\Models;

use App\Models\User;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Attendance\Database\Factories\SummaryOverrideFactory;
use Modules\Attendance\Enums\OverrideField;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;

class SummaryOverride extends Model
{
    /** @use HasFactory<SummaryOverrideFactory> */
    use Auditable, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'pay_period_id',
        'employment_id',
        'field',
        'value',
        'reason',
        'set_by',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'field' => OverrideField::class,
            'value' => 'decimal:2',
            'cancelled_at' => 'datetime',
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

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    protected static function newFactory(): SummaryOverrideFactory
    {
        return SummaryOverrideFactory::new();
    }
}
