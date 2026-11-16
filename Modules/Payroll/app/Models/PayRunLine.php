<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Payroll\Database\Factories\PayRunLineFactory;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Enums\PayRunStatus;

class PayRunLine extends Model
{
    /** @use HasFactory<PayRunLineFactory> */
    use HasFactory;

    protected $table = 'pay_run_lines';

    protected $fillable = [
        'pay_run_employee_id',
        'pay_component_id',
        'code',
        'kind',
        'sheet',
        'quantity',
        'rate',
        'amount',
        'source_type',
        'source_id',
        'is_override',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ComponentKind::class,
            'sheet' => 'integer',
            'quantity' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'is_override' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function payRunEmployee(): BelongsTo
    {
        return $this->belongsTo(PayRunEmployee::class);
    }

    public function payComponent(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class);
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
        $employee = $this->relationLoaded('payRunEmployee')
            ? $this->payRunEmployee
            : PayRunEmployee::query()->find($this->pay_run_employee_id);

        if ($employee === null) {
            return;
        }

        $run = $employee->relationLoaded('payRun')
            ? $employee->payRun
            : PayRun::query()->find($employee->pay_run_id);

        if ($run !== null && $run->status === PayRunStatus::LOCKED) {
            throw new \LogicException('A locked pay run cannot be changed.');
        }
    }

    protected static function newFactory(): PayRunLineFactory
    {
        return PayRunLineFactory::new();
    }
}
