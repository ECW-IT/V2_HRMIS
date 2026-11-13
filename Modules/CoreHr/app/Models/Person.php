<?php

namespace Modules\CoreHr\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CoreHr\Database\Factories\PersonFactory;
use Modules\CoreHr\Enums\Gender;
use Modules\CoreHr\Enums\MaritalStatus;

class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use Auditable, HasFactory;

    protected $table = 'persons';

    protected $fillable = [
        'nic',
        'nic_as_entered',
        'passport_no',
        'title',
        'first_name',
        'middle_name',
        'last_name',
        'name_with_initials',
        'calling_name',
        'date_of_birth',
        'gender',
        'marital_status',
        'nationality',
        'photo_path',
        'nic_mismatch',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'marital_status' => MaritalStatus::class,
            'nic_mismatch' => 'boolean',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(PersonContact::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(PersonAddress::class);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(PersonEmergencyContact::class);
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(PersonDependent::class);
    }

    public function qualifications(): HasMany
    {
        return $this->hasMany(PersonQualification::class);
    }

    public function experience(): HasMany
    {
        return $this->hasMany(PersonExperience::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    protected static function newFactory(): PersonFactory
    {
        return PersonFactory::new();
    }
}
