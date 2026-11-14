<?php

namespace Modules\CoreHr\Services;

use App\Support\Effective\EffectiveDating;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Services\EnrolmentService;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Enums\EventType;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Exceptions\ActiveEmploymentExists;
use Modules\CoreHr\Exceptions\EmploymentNotActive;
use Modules\CoreHr\Exceptions\InvalidEmploymentData;
use Modules\CoreHr\Exceptions\OutsideEmploymentPeriod;
use Modules\CoreHr\Models\Assignment;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\EmploymentEvent;
use Modules\CoreHr\Models\Person;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Grade;
use Modules\Organisation\Models\Job;
use Modules\Organisation\Models\Location;
use Modules\Organisation\Models\OrgUnit;

final class EmploymentService
{
    public function __construct(
        private readonly EmployeeNumberGenerator $numbers,
        private readonly EnrolmentService $enrolments,
    ) {}

    /** $employment keys: type, hire_date, epf_liable?, epf_no?, legacy_employee_no?, employee_no? (import only), employee_category_id?, staff_work_category_id?.
     *  $assignment keys: org_unit_id, job_id, grade_id, location_id, manager_employment_id? */
    public function hire(Person $person, Company $company, array $employment, array $assignment): Employment
    {
        return $this->createEmployment($person, $company, $employment, $assignment, EventType::HIRE, null);
    }

    public function rejoin(Person $person, Company $company, array $employment, array $assignment): Employment
    {
        $previous = Employment::query()
            ->where('person_id', $person->id)
            ->where('company_id', $company->id)
            ->where('status', EmploymentStatus::EXITED)
            ->orderByDesc('exit_date')
            ->orderByDesc('id')
            ->first();

        if ($previous === null) {
            throw new InvalidEmploymentData('No previous exited employment in this company.');
        }

        return $this->createEmployment($person, $company, $employment, $assignment, EventType::REJOIN, $previous->id);
    }

    public function confirm(Employment $e, \DateTimeInterface|string $on): Employment
    {
        if ($e->status !== EmploymentStatus::ACTIVE) {
            throw new EmploymentNotActive('Employment is not active.');
        }

        if ($e->type !== EmploymentType::PROBATION) {
            throw new InvalidEmploymentData('Only probation employees can be confirmed.');
        }

        $onDate = $this->dateString($on);
        if ($onDate < $this->dateString($e->hire_date)) {
            throw new OutsideEmploymentPeriod('Confirmation date is before the hire date.');
        }

        return DB::transaction(function () use ($e, $onDate) {
            $e->update([
                'type' => EmploymentType::PERMANENT,
                'confirmed_on' => $onDate,
            ]);

            $this->recordEvent($e, EventType::CONFIRMATION, $onDate);

            return $e->fresh();
        });
    }

    public function changeAssignment(Employment $e, \DateTimeInterface|string $from, array $changes, string $reason): Assignment
    {
        if ($e->status === EmploymentStatus::EXITED) {
            throw new EmploymentNotActive('Employment has exited.');
        }

        $fromDate = $this->dateString($from);
        if ($fromDate < $this->dateString($e->hire_date)) {
            throw new OutsideEmploymentPeriod('Assignment date is before the hire date.');
        }

        $current = $e->assignmentOn($fromDate);
        if ($current === null) {
            throw new OutsideEmploymentPeriod('No assignment exists on this date.');
        }

        $keys = ['org_unit_id', 'job_id', 'grade_id', 'location_id', 'manager_employment_id'];
        $fromValues = [];
        $toValues = [];
        $merged = [
            'org_unit_id' => $current->org_unit_id,
            'job_id' => $current->job_id,
            'grade_id' => $current->grade_id,
            'location_id' => $current->location_id,
            'manager_employment_id' => $current->manager_employment_id,
        ];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $changes)) {
                continue;
            }
            $newVal = $changes[$key];
            $oldVal = $merged[$key];
            if ((string) ($newVal ?? '') !== (string) ($oldVal ?? '')) {
                $fromValues[$key] = $oldVal;
                $toValues[$key] = $newVal;
                $merged[$key] = $newVal;
            }
        }

        if ($fromValues === []) {
            throw new InvalidEmploymentData('Nothing was changed.');
        }

        $this->assertAssignmentTargets($e->company_id, $merged, $e->id);

        return DB::transaction(function () use ($e, $fromDate, $merged, $reason, $fromValues, $toValues) {
            $assignment = new Assignment([
                'employment_id' => $e->id,
                'effective_from' => $fromDate,
                'org_unit_id' => $merged['org_unit_id'],
                'job_id' => $merged['job_id'],
                'grade_id' => $merged['grade_id'],
                'location_id' => $merged['location_id'],
                'manager_employment_id' => $merged['manager_employment_id'],
                'change_reason' => $reason,
            ]);

            /** @var Assignment $saved */
            $saved = EffectiveDating::insert($assignment);

            $this->recordEvent($e, EventType::ASSIGNMENT_CHANGE, $fromDate, [
                'from' => $fromValues,
                'to' => $toValues,
            ]);

            return $saved->fresh();
        });
    }

    public function suspend(Employment $e, \DateTimeInterface|string $on, string $remarks): Employment
    {
        if ($e->status !== EmploymentStatus::ACTIVE) {
            throw new EmploymentNotActive('Employment is not active.');
        }

        return DB::transaction(function () use ($e, $on, $remarks) {
            $onDate = $this->dateString($on);
            if ($onDate < $this->dateString($e->hire_date)) {
                throw new OutsideEmploymentPeriod('Suspension date is before the hire date.');
            }
            $e->update(['status' => EmploymentStatus::SUSPENDED]);
            $this->recordEvent($e, EventType::SUSPENSION, $onDate, ['remarks' => $remarks]);

            return $e->fresh();
        });
    }

    public function reinstate(Employment $e, \DateTimeInterface|string $on): Employment
    {
        if ($e->status !== EmploymentStatus::SUSPENDED) {
            throw new EmploymentNotActive('Employment is not suspended.');
        }

        return DB::transaction(function () use ($e, $on) {
            $onDate = $this->dateString($on);
            $e->update(['status' => EmploymentStatus::ACTIVE]);
            $this->recordEvent($e, EventType::REINSTATEMENT, $onDate);

            return $e->fresh();
        });
    }

    public function exit(Employment $e, \DateTimeInterface|string $on, ExitReason $reason, ?string $remarks = null): Employment
    {
        if ($e->status === EmploymentStatus::EXITED) {
            throw new EmploymentNotActive('Employment has already exited.');
        }

        $onDate = $this->dateString($on);
        if ($onDate < $this->dateString($e->hire_date)) {
            throw new OutsideEmploymentPeriod('Exit date is before the hire date.');
        }

        $latestAssignment = $e->assignments()->orderByDesc('effective_from')->first();
        if ($latestAssignment !== null && $onDate < $this->dateString($latestAssignment->effective_from)) {
            throw new OutsideEmploymentPeriod('An assignment change exists after this date.');
        }

        return DB::transaction(function () use ($e, $onDate, $reason, $remarks) {
            $e->update([
                'status' => EmploymentStatus::EXITED,
                'exit_date' => $onDate,
                'exit_reason' => $reason,
                'exit_remarks' => $remarks,
            ]);

            EffectiveDating::close(Assignment::class, $e->id, $onDate);
            $this->enrolments->endAllFor($e, $onDate);
            $this->recordEvent($e, EventType::EXIT, $onDate);

            return $e->fresh();
        });
    }

    /** $newEmployment keys: epf_no? (type and epf_liable are copied from $from). */
    public function transferToSisterCompany(
        Employment $from,
        Company $to,
        \DateTimeInterface|string $lastDay,
        array $assignment,
        array $newEmployment = [],
    ): Employment {
        if ((int) $to->id === (int) $from->company_id) {
            throw new InvalidEmploymentData('Transfer must be to a different company.');
        }

        return DB::transaction(function () use ($from, $to, $lastDay, $assignment, $newEmployment) {
            $lastDayDate = $this->dateString($lastDay);
            $hireDate = CarbonImmutable::createFromFormat('Y-m-d', $lastDayDate)->addDay()->toDateString();

            $exited = $this->exit($from, $lastDayDate, ExitReason::SISTER_COMPANY_TRANSFER);

            $hirePayload = [
                'type' => $from->type->value,
                'hire_date' => $hireDate,
                'epf_liable' => $from->epf_liable,
                'epf_no' => $newEmployment['epf_no'] ?? null,
                'confirmed_on' => $from->confirmed_on?->toDateString(),
            ];

            $new = $this->createEmployment(
                $from->person,
                $to,
                $hirePayload,
                $assignment,
                EventType::HIRE,
                $from->id,
            );

            EmploymentEvent::query()
                ->where('employment_id', $exited->id)
                ->where('type', EventType::EXIT)
                ->latest('id')
                ->first()
                ?->update([
                    'type' => EventType::SISTER_COMPANY_TRANSFER_OUT,
                    'related_employment_id' => $new->id,
                ]);

            EmploymentEvent::query()
                ->where('employment_id', $new->id)
                ->where('type', EventType::HIRE)
                ->latest('id')
                ->first()
                ?->update([
                    'type' => EventType::SISTER_COMPANY_TRANSFER_IN,
                    'related_employment_id' => $exited->id,
                ]);

            return $new->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $employment
     * @param  array<string, mixed>  $assignment
     */
    private function createEmployment(
        Person $person,
        Company $company,
        array $employment,
        array $assignment,
        EventType $eventType,
        ?int $previousEmploymentId,
    ): Employment {
        $hireDate = $this->dateString($employment['hire_date'] ?? null);
        if ($hireDate === '') {
            throw new InvalidEmploymentData('Hire date is required.');
        }

        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $hire = CarbonImmutable::createFromFormat('Y-m-d', $hireDate)->startOfDay();
        if ($hire->greaterThan($today->addDays(60))) {
            throw new InvalidEmploymentData('Hire date is too far in the future.');
        }

        $activeExists = Employment::query()
            ->where('person_id', $person->id)
            ->where('status', '!=', EmploymentStatus::EXITED)
            ->exists();

        if ($activeExists) {
            throw new ActiveEmploymentExists('This person already has an active employment.');
        }

        $type = $employment['type'] instanceof EmploymentType
            ? $employment['type']
            : EmploymentType::from((string) $employment['type']);

        $epfLiable = (bool) ($employment['epf_liable'] ?? true);
        $epfNo = $employment['epf_no'] ?? null;

        if ($type === EmploymentType::INTERN) {
            $epfLiable = false;
            $epfNo = null;
        } elseif ($epfLiable && ($epfNo === null || $epfNo === '')) {
            throw new InvalidEmploymentData('EPF number is required.');
        }

        if ($epfNo !== null && $epfNo !== '') {
            $epfTaken = Employment::query()
                ->where('company_id', $company->id)
                ->where('epf_no', $epfNo)
                ->exists();
            if ($epfTaken) {
                throw new InvalidEmploymentData('EPF number is already in use in this company.');
            }
        }

        $confirmationDue = $employment['confirmation_due_date'] ?? null;
        if ($type === EmploymentType::PROBATION && $confirmationDue === null) {
            $confirmationDue = $hire->addMonths(6)->toDateString();
        }

        $this->assertAssignmentTargets($company->id, $assignment);

        return DB::transaction(function () use (
            $person,
            $company,
            $employment,
            $assignment,
            $eventType,
            $previousEmploymentId,
            $hireDate,
            $type,
            $epfLiable,
            $epfNo,
            $confirmationDue,
        ) {
            if (! empty($employment['employee_no'])) {
                $employeeNo = (string) $employment['employee_no'];
            } else {
                $employeeNo = $this->numbers->next(
                    $company,
                    fn (string $n) => Employment::query()
                        ->where('company_id', $company->id)
                        ->where('employee_no', $n)
                        ->exists(),
                );
            }

            try {
                $record = Employment::query()->create([
                    'person_id' => $person->id,
                    'company_id' => $company->id,
                    'employee_no' => $employeeNo,
                    'legacy_employee_no' => $employment['legacy_employee_no'] ?? null,
                    'type' => $type,
                    'status' => EmploymentStatus::ACTIVE,
                    'hire_date' => $hireDate,
                    'confirmation_due_date' => $confirmationDue,
                    'confirmed_on' => $employment['confirmed_on'] ?? null,
                    'epf_liable' => $epfLiable,
                    'epf_no' => $epfNo,
                    'previous_employment_id' => $previousEmploymentId,
                    'employee_category_id' => $employment['employee_category_id'] ?? null,
                    'staff_work_category_id' => $employment['staff_work_category_id'] ?? null,
                ]);
            } catch (QueryException $e) {
                if ($this->isActivePersonUniqueViolation($e)) {
                    throw new ActiveEmploymentExists('This person is already employed in another company.');
                }

                throw $e;
            }

            $assignmentModel = new Assignment([
                'employment_id' => $record->id,
                'effective_from' => $hireDate,
                'org_unit_id' => $assignment['org_unit_id'],
                'job_id' => $assignment['job_id'],
                'grade_id' => $assignment['grade_id'],
                'location_id' => $assignment['location_id'],
                'manager_employment_id' => $assignment['manager_employment_id'] ?? null,
            ]);

            EffectiveDating::insert($assignmentModel);
            $this->recordEvent($record, $eventType, $hireDate);

            return $record->fresh(['assignments', 'events']);
        });
    }

    /** @param  array<string, mixed>  $assignment */
    private function assertAssignmentTargets(int $companyId, array $assignment, ?int $selfEmploymentId = null): void
    {
        $orgUnit = OrgUnit::query()->find($assignment['org_unit_id'] ?? null);
        if ($orgUnit === null || (int) $orgUnit->company_id !== $companyId || ! $orgUnit->is_active) {
            throw new InvalidEmploymentData('Organisation unit is invalid for this company.');
        }

        $job = Job::query()->find($assignment['job_id'] ?? null);
        if ($job === null || ! $job->is_active) {
            throw new InvalidEmploymentData('Job is invalid.');
        }

        $grade = Grade::query()->find($assignment['grade_id'] ?? null);
        if ($grade === null || ! $grade->is_active) {
            throw new InvalidEmploymentData('Grade is invalid.');
        }

        $location = Location::query()->find($assignment['location_id'] ?? null);
        if ($location === null || ! $location->is_active) {
            throw new InvalidEmploymentData('Location is invalid.');
        }

        $managerId = $assignment['manager_employment_id'] ?? null;
        if ($managerId === null) {
            return;
        }

        if ($selfEmploymentId !== null && (int) $managerId === (int) $selfEmploymentId) {
            throw new InvalidEmploymentData('An employee cannot be their own manager.');
        }

        $manager = Employment::query()->find($managerId);
        if (
            $manager === null
            || $manager->status !== EmploymentStatus::ACTIVE
            || (int) $manager->company_id !== $companyId
        ) {
            throw new InvalidEmploymentData('Manager must be an active employment in the same company.');
        }
    }

    /** @param  array<string, mixed>|null  $details */
    private function recordEvent(
        Employment $employment,
        EventType $type,
        string $eventDate,
        ?array $details = null,
        ?int $relatedEmploymentId = null,
        ?int $approvalRequestId = null,
    ): EmploymentEvent {
        return EmploymentEvent::query()->create([
            'employment_id' => $employment->id,
            'type' => $type,
            'event_date' => $eventDate,
            'details' => $details,
            'related_employment_id' => $relatedEmploymentId,
            'approval_request_id' => $approvalRequestId,
            'created_by' => Auth::id(),
        ]);
    }

    private function isActivePersonUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062
            && str_contains($e->getMessage(), 'employments_active_person_id_unique');
    }

    private function dateString(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
