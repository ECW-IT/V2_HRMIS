<?php

namespace Modules\CoreHr\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CoreHr\Database\Factories\PersonContactFactory;
use Modules\CoreHr\Enums\ContactType;

class PersonContact extends Model
{
    /** @use HasFactory<PersonContactFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'person_id',
        'type',
        'value',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContactType::class,
            'is_primary' => 'boolean',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    protected static function newFactory(): PersonContactFactory
    {
        return PersonContactFactory::new();
    }
}
