<?php

namespace Modules\CoreHr\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CoreHr\Database\Factories\PersonQualificationFactory;

class PersonQualification extends Model
{
    /** @use HasFactory<PersonQualificationFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'person_id',
        'type',
        'institute',
        'title',
        'year',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    protected static function newFactory(): PersonQualificationFactory
    {
        return PersonQualificationFactory::new();
    }
}
