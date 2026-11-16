<?php

namespace Modules\Payroll\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Payroll\Database\Factories\PayRunEventFactory;

class PayRunEvent extends Model
{
    /** @use HasFactory<PayRunEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'pay_run_events';

    protected $fillable = [
        'pay_run_id',
        'event',
        'note',
        'user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function payRun(): BelongsTo
    {
        return $this->belongsTo(PayRun::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('Pay run events are append-only.');
        }

        if ($this->created_at === null) {
            $this->created_at = now();
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new \LogicException('Pay run events cannot be deleted.');
    }

    protected static function newFactory(): PayRunEventFactory
    {
        return PayRunEventFactory::new();
    }
}
