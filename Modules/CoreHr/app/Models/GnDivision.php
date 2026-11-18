<?php

namespace Modules\CoreHr\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CoreHr\Database\Factories\GnDivisionFactory;

class GnDivision extends Model
{
    /** @use HasFactory<GnDivisionFactory> */
    use HasFactory;

    protected $table = 'gn_divisions';

    protected $fillable = [
        'district_id',
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

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /** @return list<string> */
    public static function namesForDistrictName(string $districtName): array
    {
        if ($districtName === '') {
            return [];
        }

        $districtId = District::query()->where('name', $districtName)->value('id');
        if ($districtId === null) {
            return [];
        }

        return self::query()
            ->where('district_id', $districtId)
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => (string) $name)
            ->all();
    }

    protected static function newFactory(): GnDivisionFactory
    {
        return GnDivisionFactory::new();
    }
}
