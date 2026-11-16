<?php

namespace Modules\Payroll\Models;

use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Company\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Calendar\Models\PayPeriod;
use Modules\Payroll\Database\Factories\PayRunFactory;
use Modules\Payroll\Enums\PayRunStatus;

class PayRun extends Model
{
    /** @use HasFactory<PayRunFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $table = 'pay_runs';

    protected $fillable = [
        'company_id',
        'pay_period_id',
        'status',
        'time_summary_version',
        'employee_count',
        'error_count',
        'gross_sheet1',
        'gross_sheet2',
        'total_deductions',
        'net_pay',
        'employer_total',
        'carried_forward_out',
        'calculated_by',
        'calculated_at',
        'approved_by',
        'approved_at',
        'locked_by',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayRunStatus::class,
            'time_summary_version' => 'integer',
            'employee_count' => 'integer',
            'error_count' => 'integer',
            'gross_sheet1' => 'decimal:2',
            'gross_sheet2' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'employer_total' => 'decimal:2',
            'carried_forward_out' => 'decimal:2',
            'calculated_at' => 'datetime',
            'approved_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function payPeriod(): BelongsTo
    {
        return $this->belongsTo(PayPeriod::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(PayRunEmployee::class);
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(PayRunOverride::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PayRunEvent::class);
    }

    public function calculatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isLocked(): bool
    {
        return $this->status === PayRunStatus::LOCKED;
    }

    public function save(array $options = []): bool
    {
        $original = $this->getOriginal('status');
        $wasLocked = $original === PayRunStatus::LOCKED
            || $original === PayRunStatus::LOCKED->value;
        if ($this->exists && $wasLocked) {
            throw new \LogicException('A locked pay run cannot be changed.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        if ($this->status === PayRunStatus::LOCKED) {
            throw new \LogicException('A locked pay run cannot be deleted.');
        }

        return parent::delete();
    }

    protected static function newFactory(): PayRunFactory
    {
        return PayRunFactory::new();
    }
}
