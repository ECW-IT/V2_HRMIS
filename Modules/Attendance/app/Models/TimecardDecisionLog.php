<?php

namespace Modules\Attendance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Attendance\Database\Factories\TimecardDecisionLogFactory;
use Modules\CoreHr\Models\Employment;

class TimecardDecisionLog extends Model
{
    /** @use HasFactory<TimecardDecisionLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'timecard_id',
        'employment_id',
        'work_date',
        'kind',
        'old_value',
        'new_value',
        'reason',
        'user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'old_value' => 'integer',
            'new_value' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function timecard(): BelongsTo
    {
        return $this->belongsTo(Timecard::class);
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Timecard decision logs cannot be updated.');
    }

    public function delete(): ?bool
    {
        throw new \LogicException('Timecard decision logs cannot be deleted.');
    }

    protected static function newFactory(): TimecardDecisionLogFactory
    {
        return TimecardDecisionLogFactory::new();
    }
}
