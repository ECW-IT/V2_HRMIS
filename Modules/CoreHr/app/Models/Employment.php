<?php

namespace Modules\CoreHr\Models;

use App\Support\Audit\Auditable;
use App\Support\Company\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Attendance\Models\EmploymentTimeProfile;
use Modules\CoreHr\Database\Factories\EmploymentFactory;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Exceptions\InvalidEmploymentData;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Models\StaffWorkCategory;

class Employment extends Model
{
    /** @use HasFactory<EmploymentFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected static function booted(): void
    {
        static::updating(function (self $employment): void {
            if ($employment->isDirty(['employee_no', 'epf_no'])) {
                throw new InvalidEmploymentData('Employee number and EPF number cannot be changed.');
            }
        });
    }

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
        'hrmis_active',
        'attendance_active',
        'payroll_active',
        'hostel_available',
        'food_breakfast',
        'food_lunch',
        'food_dinner',
        'exit_date',
        'exit_reason',
        'exit_remarks',
        'previous_employment_id',
        'employee_category_id',
        'staff_work_category_id',
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
            'hrmis_active' => 'boolean',
            'attendance_active' => 'boolean',
            'payroll_active' => 'boolean',
            'hostel_available' => 'boolean',
            'food_breakfast' => 'boolean',
            'food_lunch' => 'boolean',
            'food_dinner' => 'boolean',
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

    public function timeProfiles(): HasMany
    {
        return $this->hasMany(EmploymentTimeProfile::class);
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

    public function staffWorkCategory(): BelongsTo
    {
        return $this->belongsTo(StaffWorkCategory::class);
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

    public function isResigned(): bool
    {
        return ! (bool) $this->hrmis_active;
    }

    protected static function newFactory(): EmploymentFactory
    {
        return EmploymentFactory::new();
    }
}
