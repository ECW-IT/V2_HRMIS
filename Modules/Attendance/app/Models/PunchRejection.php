<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Attendance\Database\Factories\PunchRejectionFactory;
use Modules\Attendance\Enums\RejectionReason;

class PunchRejection extends Model
{
    /** @use HasFactory<PunchRejectionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'device_id',
        'payload',
        'reason',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'reason' => RejectionReason::class,
            'received_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    protected static function newFactory(): PunchRejectionFactory
    {
        return PunchRejectionFactory::new();
    }
}
