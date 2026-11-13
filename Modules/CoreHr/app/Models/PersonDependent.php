<?php

namespace Modules\CoreHr\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CoreHr\Database\Factories\PersonDependentFactory;

class PersonDependent extends Model
{
    /** @use HasFactory<PersonDependentFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'person_id',
        'name',
        'relationship',
        'date_of_birth',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    protected static function newFactory(): PersonDependentFactory
    {
        return PersonDependentFactory::new();
    }
}
