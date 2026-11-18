<?php

namespace Modules\CoreHr\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CoreHr\Database\Factories\DistrictFactory;

class District extends Model
{
    /** @use HasFactory<DistrictFactory> */
    use HasFactory;

    protected $table = 'districts';

    protected $fillable = [
        'code',
        'name',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function gnDivisions(): HasMany
    {
        return $this->hasMany(GnDivision::class);
    }

    protected static function newFactory(): DistrictFactory
    {
        return DistrictFactory::new();
    }
}
