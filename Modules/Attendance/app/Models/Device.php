<?php

namespace Modules\Attendance\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Attendance\Database\Factories\DeviceFactory;
use Modules\Organisation\Models\Location;

class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use Auditable, HasFactory;

    /** @var list<string> */
    protected array $auditExcluded = ['api_token_hash', 'last_seen_at'];

    protected $fillable = [
        'serial_no',
        'name',
        'location_id',
        'device_group',
        'ip_address',
        'api_token_hash',
        'is_active',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function punches(): HasMany
    {
        return $this->hasMany(Punch::class);
    }

    protected static function newFactory(): DeviceFactory
    {
        return DeviceFactory::new();
    }
}
