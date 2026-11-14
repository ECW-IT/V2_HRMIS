<?php

namespace Modules\Attendance\Models;

use App\Support\Audit\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Attendance\Database\Factories\ShiftFactory;

class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'start_time',
        'end_time',
        'late_from',
        'morning_cutoff',
        'half_day_from',
        'early_leave_from',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(ShiftBreak::class);
    }

    public function timeProfiles(): HasMany
    {
        return $this->hasMany(EmploymentTimeProfile::class);
    }

    public function at(string $date, string $field): CarbonImmutable
    {
        $time = (string) $this->getAttribute($field);

        return CarbonImmutable::parse($date.' '.$time, config('app.timezone'));
    }

    protected static function newFactory(): ShiftFactory
    {
        return ShiftFactory::new();
    }
}
