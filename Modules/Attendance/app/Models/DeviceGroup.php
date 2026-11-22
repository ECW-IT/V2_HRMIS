<?php

namespace Modules\Attendance\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Attendance\Database\Factories\DeviceGroupFactory;

class DeviceGroup extends Model
{
    /** @use HasFactory<DeviceGroupFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): DeviceGroupFactory
    {
        return DeviceGroupFactory::new();
    }
}
