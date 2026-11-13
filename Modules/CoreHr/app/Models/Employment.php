<?php

namespace Modules\CoreHr\Models;

use App\Support\Audit\Auditable;
use App\Support\Company\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CoreHr\Database\Factories\EmploymentFactory;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Enums\ExitReason;
use Modules\Organisation\Models\EmployeeCategory;

class Employment extends Model
{
    /** @use HasFactory<EmploymentFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'person_id',
        'company_id',
        'employee_no',
        'legacy_employee_no',
        'type',
        'status',
        'hire_date',
        'confirmation_due_date',
        'confirmed_on',
        'epf_liable',
        'epf_no',
        'exit_date',
        'exit_reason',
        'exit_remarks',
        'previous_employment_id',
        'employee_category_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => EmploymentType::class,
            'status' => EmploymentStatus::class,
            'exit_reason' => ExitReason::class,
            'hire_date' => 'date',
            'confirmation_due_date' => 'date',
            'confirmed_on' => 'date',
            'exit_date' => 'date',
            'epf_liable' => 'boolean',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(EmploymentEvent::class);
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_employment_id');
    }

    public function employeeCategory(): BelongsTo
    {
        return $this->belongsTo(EmployeeCategory::class);
    }

    public function currentAssignment(): ?Assignment
    {
        return $this->assignmentOn(CarbonImmutable::now(config('app.timezone'))->toDateString());
    }

    public function assignmentOn(\DateTimeInterface|string $date): ?Assignment
    {
        return $this->assignments()->effectiveOn($date)->first();
    }

    public function serviceStartDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->hire_date->toDateString())->startOfDay();
    }

    public function isActive(): bool
    {
        return $this->status === EmploymentStatus::ACTIVE;
    }

    protected static function newFactory(): EmploymentFactory
    {
        return EmploymentFactory::new();
    }
}
