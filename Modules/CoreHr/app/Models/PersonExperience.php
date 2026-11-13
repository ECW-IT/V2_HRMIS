<?php

namespace Modules\CoreHr\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CoreHr\Database\Factories\PersonExperienceFactory;

class PersonExperience extends Model
{
    /** @use HasFactory<PersonExperienceFactory> */
    use Auditable, HasFactory;

    protected $table = 'person_experience';

    protected $fillable = [
        'person_id',
        'employer',
        'job_title',
        'from_date',
        'to_date',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    protected static function newFactory(): PersonExperienceFactory
    {
        return PersonExperienceFactory::new();
    }
}
