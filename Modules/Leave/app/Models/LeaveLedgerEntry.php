<?php

namespace Modules\Leave\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;
use Modules\Leave\Database\Factories\LeaveLedgerEntryFactory;

class LeaveLedgerEntry extends Model
{
    /** @use HasFactory<LeaveLedgerEntryFactory> */
    use HasFactory;

    protected $table = 'leave_ledger';

    public $timestamps = false;

    protected $fillable = [
        'employment_id',
        'leave_type_id',
        'pay_period_id',
        'kind',
        'days',
        'leave_request_id',
        'note',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'decimal:1',
            'created_at' => 'datetime',
        ];
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function payPeriod(): BelongsTo
    {
        return $this->belongsTo(PayPeriod::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Leave ledger entries cannot be updated.');
    }

    public function delete(): ?bool
    {
        throw new \LogicException('Leave ledger entries cannot be deleted.');
    }

    protected static function newFactory(): LeaveLedgerEntryFactory
    {
        return LeaveLedgerEntryFactory::new();
    }
}
