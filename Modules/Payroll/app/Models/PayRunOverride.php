<?php

namespace Modules\Payroll\Models;

use App\Models\User;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Database\Factories\PayRunOverrideFactory;
use Modules\Payroll\Enums\PayRunStatus;

class PayRunOverride extends Model
{
    /** @use HasFactory<PayRunOverrideFactory> */
    use Auditable, HasFactory;

    protected $table = 'pay_run_overrides';

    protected $fillable = [
        'pay_run_id',
        'employment_id',
        'pay_component_id',
        'amount',
        'reason',
        'set_by',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function payRun(): BelongsTo
    {
        return $this->belongsTo(PayRun::class);
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function payComponent(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class);
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    public function save(array $options = []): bool
    {
        $this->assertRunNotLocked();

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        $this->assertRunNotLocked();

        return parent::delete();
    }

    private function assertRunNotLocked(): void
    {
        $run = $this->relationLoaded('payRun') ? $this->payRun : PayRun::query()->find($this->pay_run_id);
        if ($run !== null && $run->status === PayRunStatus::LOCKED) {
            throw new \LogicException('A locked pay run cannot be changed.');
        }
    }

    protected static function newFactory(): PayRunOverrideFactory
    {
        return PayRunOverrideFactory::new();
    }
}
