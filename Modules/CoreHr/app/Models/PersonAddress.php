<?php

namespace Modules\CoreHr\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CoreHr\Database\Factories\PersonAddressFactory;
use Modules\CoreHr\Enums\AddressType;

class PersonAddress extends Model
{
    /** @use HasFactory<PersonAddressFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'person_id',
        'type',
        'line1',
        'line2',
        'city',
        'district',
        'postal_code',
    ];

    protected function casts(): array
    {
        return [
            'type' => AddressType::class,
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    protected static function newFactory(): PersonAddressFactory
    {
        return PersonAddressFactory::new();
    }
}
