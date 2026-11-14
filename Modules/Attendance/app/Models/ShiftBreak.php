<?php

namespace Modules\Attendance\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Attendance\Database\Factories\ShiftBreakFactory;

class ShiftBreak extends Model
{
    /** @use HasFactory<ShiftBreakFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'shift_id',
        'name',
        'start_time',
        'end_time',
        'is_paid',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    protected static function newFactory(): ShiftBreakFactory
    {
        return ShiftBreakFactory::new();
    }
}
