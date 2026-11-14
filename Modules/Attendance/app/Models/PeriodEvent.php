<?php

namespace Modules\Attendance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Attendance\Database\Factories\PeriodEventFactory;
use Modules\Calendar\Models\PayPeriod;

class PeriodEvent extends Model
{
    /** @use HasFactory<PeriodEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'pay_period_id',
        'event',
        'version',
        'reason',
        'user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function payPeriod(): BelongsTo
    {
        return $this->belongsTo(PayPeriod::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Period events cannot be updated.');
    }

    public function delete(): ?bool
    {
        throw new \LogicException('Period events cannot be deleted.');
    }

    protected static function newFactory(): PeriodEventFactory
    {
        return PeriodEventFactory::new();
    }
}
