<?php

namespace Modules\Organisation\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Organisation\Database\Factories\LocationFactory;

class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): LocationFactory
    {
        return LocationFactory::new();
    }
}
