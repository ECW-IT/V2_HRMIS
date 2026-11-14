<?php

namespace Modules\Calendar\Models;

use App\Support\Audit\Auditable;
use App\Support\Company\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Calendar\Database\Factories\HolidayFactory;
use Modules\Calendar\Enums\HolidayKind;

class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'date',
        'name',
        'kind',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'kind' => HolidayKind::class,
        ];
    }

    protected static function newFactory(): HolidayFactory
    {
        return HolidayFactory::new();
    }
}
