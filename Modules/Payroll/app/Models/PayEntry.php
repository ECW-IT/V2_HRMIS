<?php

namespace Modules\Payroll\Models;

use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Company\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Database\Factories\PayEntryFactory;
use Modules\Payroll\Enums\PayEntryStatus;

class PayEntry extends Model
{
    /** @use HasFactory<PayEntryFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $table = 'pay_entries';

    protected $fillable = [
        'company_id',
        'pay_period_id',
        'employment_id',
        'pay_component_id',
        'quantity',
        'amount',
        'reference',
        'note',
        'status',
        'created_by',
        'cancelled_by',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'amount' => 'decimal:2',
            'status' => PayEntryStatus::class,
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

    public function payComponent(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    protected static function newFactory(): PayEntryFactory
    {
        return PayEntryFactory::new();
    }
}
