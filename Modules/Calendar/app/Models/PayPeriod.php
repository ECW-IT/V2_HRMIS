<?php

namespace Modules\Calendar\Models;

use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Company\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Calendar\Database\Factories\PayPeriodFactory;
use Modules\Calendar\Enums\PeriodStatus;

class PayPeriod extends Model
{
    /** @use HasFactory<PayPeriodFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'year',
        'month',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by',
        'close_version',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => PeriodStatus::class,
            'closed_at' => 'datetime',
            'close_version' => 'integer',
        ];
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function expectedDays(): HasMany
    {
        return $this->hasMany(PeriodExpectedDays::class);
    }

    protected static function newFactory(): PayPeriodFactory
    {
        return PayPeriodFactory::new();
    }
}
