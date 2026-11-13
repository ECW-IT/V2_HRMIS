<?php

namespace Modules\CoreHr\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CoreHr\Database\Factories\PersonEmergencyContactFactory;

class PersonEmergencyContact extends Model
{
    /** @use HasFactory<PersonEmergencyContactFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'person_id',
        'name',
        'relationship',
        'phone',
        'address',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    protected static function newFactory(): PersonEmergencyContactFactory
    {
        return PersonEmergencyContactFactory::new();
    }
}
