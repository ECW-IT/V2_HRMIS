<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'company_id',
        'auditable_type',
        'auditable_id',
        'event',
        'old_values',
        'new_values',
        'reason',
        'ip',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('Audit log rows cannot be updated.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new \LogicException('Audit log rows cannot be deleted.');
    }
}
