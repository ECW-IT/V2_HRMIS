<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Attendance\Database\Factories\PunchFactory;
use Modules\Attendance\Enums\PunchResolution;
use Modules\CoreHr\Models\Employment;

class Punch extends Model
{
    /** @use HasFactory<PunchFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'device_id',
        'device_user_id',
        'punched_at',
        'employment_id',
        'resolution',
        'verify_mode',
        'raw_state',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'received_at' => 'datetime',
            'resolution' => PunchResolution::class,
            'device_user_id' => 'integer',
            'verify_mode' => 'integer',
            'raw_state' => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            $dirty = array_keys($this->getDirty());
            $allowed = ['employment_id', 'resolution'];
            if (array_diff($dirty, $allowed) !== []) {
                throw new \LogicException('Punches are append-only.');
            }
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new \LogicException('Punches cannot be deleted.');
    }

    protected static function newFactory(): PunchFactory
    {
        return PunchFactory::new();
    }
}
