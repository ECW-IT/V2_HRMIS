<?php

namespace Modules\LegacyImport\Services;

use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Attendance\Models\BiometricEnrolment;
use Modules\CoreHr\Enums\EventType;
use Modules\CoreHr\Models\EmployeeBankAccount;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\EmploymentEvent;
use Modules\CoreHr\Services\BankAccountService;
use Modules\LegacyImport\Exports\LegacySyncExport;
use Modules\LegacyImport\Models\LegacySyncRun;
use Modules\Organisation\Enums\OrgUnitType;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Grade;
use Modules\Organisation\Models\Job;
use Modules\Organisation\Models\Location;
use Modules\Organisation\Models\OrgUnit;

final class LegacySyncExporter
{
    private const HIRE_TYPES = [
        EventType::HIRE->value,
        EventType::REJOIN->value,
        EventType::SISTER_COMPANY_TRANSFER_IN->value,
    ];

    private const EXIT_TYPES = [
        EventType::EXIT->value,
        EventType::SISTER_COMPANY_TRANSFER_OUT->value,
    ];

    private const CHANGE_TYPES = [
        EventType::ASSIGNMENT_CHANGE->value,
        EventType::CONFIRMATION->value,
        EventType::TYPE_CHANGE->value,
        EventType::SUSPENSION->value,
        EventType::REINSTATEMENT->value,
    ];

    private const FIELD_LABELS = [
        'org_unit_id' => 'Department / section',
        'job_id' => 'Job',
        'grade_id' => 'Grade',
        'location_id' => 'Location',
        'manager_employment_id' => 'Reports to',
    ];

    public function __construct(private readonly BankAccountService $banks) {}

    /** Builds the export for (from, to] for one company. Returns the run. */
    public function run(Company $company, ?\DateTimeInterface $since = null): LegacySyncRun
    {
        $to = CarbonImmutable::now(config('app.timezone'));

        if ($since !== null) {
            $from = CarbonImmutable::instance($since)->timezone(config('app.timezone'));
        } else {
            $previous = LegacySyncRun::query()
                ->where('company_id', $company->id)
                ->orderByDesc('id')
                ->first();
            $from = $previous !== null
                ? CarbonImmutable::parse($previous->to_at)->timezone(config('app.timezone'))
                : $to->startOfDay();
        }

        $rows = $this->collect($company, $from, $to);

        $hireCount = $rows['hires']->count();
        $exitCount = $rows['exits']->count();
        $changeCount = $rows['changes']->count();
        $filePath = '';

        if ($hireCount + $exitCount + $changeCount > 0) {
            $relative = 'legacy-sync/legacy-sync-'.$company->code.'-'.$to->format('Ymd-His').'.xlsx';
            Storage::disk('private')->makeDirectory('legacy-sync');
            Excel::store(new LegacySyncExport($rows), $relative, 'private');
            $filePath = $relative;
        }

        return LegacySyncRun::query()->create([
            'company_id' => $company->id,
            'from_at' => $from,
            'to_at' => $to,
            'file_path' => $filePath,
            'new_hires' => $hireCount,
            'exits' => $exitCount,
            'changes' => $changeCount,
            'applied_by' => null,
            'applied_at' => null,
            'created_at' => $to,
        ]);
    }

    /**
     * @return array{hires: Collection<int, array<string, mixed>>, exits: Collection<int, array<string, mixed>>, changes: Collection<int, array<string, mixed>>}
     */
    public function collect(Company $company, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $fromAt = CarbonImmutable::instance($from);
        $toAt = CarbonImmutable::instance($to);

        $employmentIds = Employment::query()
            ->where('company_id', $company->id)
            ->pluck('id');

        $events = EmploymentEvent::query()
            ->with([
                'employment.person',
                'employment.company',
                'relatedEmployment',
            ])
            ->whereIn('employment_id', $employmentIds)
            ->where('created_at', '>', $fromAt)
            ->where('created_at', '<=', $toAt)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $hires = collect();
        $exits = collect();
        $changes = collect();

        foreach ($events as $event) {
            $type = $event->type->value;
            if (in_array($type, self::HIRE_TYPES, true)) {
                $hires->push($this->hireRow($event));
            } elseif (in_array($type, self::EXIT_TYPES, true)) {
                $exits->push($this->exitRow($event));
            } elseif (in_array($type, self::CHANGE_TYPES, true)) {
                $changes->push($this->changeRow($event));
            }
        }

        foreach ($this->bankChangeRows($company, $fromAt, $toAt) as $row) {
            $changes->push($row);
        }

        return [
            'hires' => $hires->values(),
            'exits' => $exits->values(),
            'changes' => $changes->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function hireRow(EmploymentEvent $event): array
    {
        $e = $event->employment;
        $person = $e->person;
        $assignment = $e->assignmentOn($e->hire_date) ?? $e->currentAssignment();
        $assignment?->loadMissing(['orgUnit.parent', 'job', 'grade']);
        $orgUnit = $assignment?->orgUnit;
        [$department, $section] = $this->departmentSection($orgUnit);

        $bank = $this->banks->on($e, $e->hire_date);
        $bank?->loadMissing(['bank', 'branch']);
        $enrolment = BiometricEnrolment::query()
            ->where('employment_id', $e->id)
            ->whereNull('valid_to')
            ->orderByDesc('id')
            ->first()
            ?? BiometricEnrolment::query()
                ->where('employment_id', $e->id)
                ->orderByDesc('id')
                ->first();

        $oldEmployeeNo = null;
        if (in_array($event->type, [EventType::REJOIN, EventType::SISTER_COMPANY_TRANSFER_IN], true)) {
            $previous = $event->relatedEmployment;
            if ($previous !== null) {
                $oldEmployeeNo = $previous->employee_no;
            } elseif ($e->previous_employment_id !== null) {
                $oldEmployeeNo = DB::table('employments')
                    ->where('id', $e->previous_employment_id)
                    ->value('employee_no');
            }
        }

        return [
            'Company' => $e->company?->code ?? '',
            'Employee no.' => $e->employee_no,
            'Old employee no.' => $oldEmployeeNo ?? '',
            'Name with initials' => $person?->name_with_initials ?? '',
            'Calling name' => $person?->calling_name ?? '',
            'NIC' => $person?->nic ?? '',
            'Hire date' => $e->hire_date?->toDateString() ?? '',
            'Type' => $e->type->value,
            'Department' => $department,
            'Section' => $section,
            'Job' => $assignment?->job?->name ?? '',
            'Grade' => $assignment?->grade?->name ?? '',
            'EPF no.' => $e->epf_no ?? '',
            'Bank' => $bank?->bank?->name ?? '',
            'Branch' => $bank?->branch?->name ?? '',
            'Account no.' => $bank?->account_no ?? '',
            'Fingerprint group' => $enrolment?->device_group ?? '',
            'Fingerprint ID' => $enrolment?->device_user_id ?? '',
        ];
    }

    /** @return array<string, mixed> */
    private function exitRow(EmploymentEvent $event): array
    {
        $e = $event->employment;
        $person = $e->person;

        return [
            'Company' => $e->company?->code ?? '',
            'Employee no.' => $e->employee_no,
            'Legacy employee no.' => $e->legacy_employee_no ?? '',
            'Name with initials' => $person?->name_with_initials ?? '',
            'Last working day' => $e->exit_date?->toDateString() ?? $event->event_date?->toDateString() ?? '',
            'Reason' => $e->exit_reason?->value ?? '',
            'Remarks' => $e->exit_remarks ?? '',
        ];
    }

    /** @return array<string, mixed> */
    private function changeRow(EmploymentEvent $event): array
    {
        $e = $event->employment;
        $person = $e->person;
        $from = '';
        $to = '';

        if ($event->type === EventType::ASSIGNMENT_CHANGE) {
            [$from, $to] = $this->formatAssignmentChange($event->details ?? []);
        }

        return [
            'Company' => $e->company?->code ?? '',
            'Employee no.' => $e->employee_no,
            'Legacy employee no.' => $e->legacy_employee_no ?? '',
            'Name with initials' => $person?->name_with_initials ?? '',
            'Change' => $this->changeLabel($event->type),
            'Effective date' => $event->event_date?->toDateString() ?? '',
            'From' => $from,
            'To' => $to,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bankChangeRows(Company $company, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $audits = AuditLog::query()
            ->where('auditable_type', EmployeeBankAccount::class)
            ->where('event', 'CREATED')
            ->where('created_at', '>', $from)
            ->where('created_at', '<=', $to)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $rows = [];

        foreach ($audits as $audit) {
            $account = EmployeeBankAccount::query()->find($audit->auditable_id);
            if ($account === null) {
                continue;
            }

            $hadPrior = EmployeeBankAccount::query()
                ->where('employment_id', $account->employment_id)
                ->where('id', '<', $account->id)
                ->exists();

            if (! $hadPrior) {
                continue;
            }

            $e = Employment::query()
                ->with(['person', 'company'])
                ->where('company_id', $company->id)
                ->find($account->employment_id);
            if ($e === null) {
                continue;
            }

            $prior = EmployeeBankAccount::query()
                ->where('employment_id', $account->employment_id)
                ->where('id', '<', $account->id)
                ->orderByDesc('id')
                ->first();

            $rows[] = [
                'Company' => $e->company?->code ?? '',
                'Employee no.' => $e->employee_no,
                'Legacy employee no.' => $e->legacy_employee_no ?? '',
                'Name with initials' => $e->person?->name_with_initials ?? '',
                'Change' => 'Bank account',
                'Effective date' => $account->effective_from?->toDateString() ?? '',
                'From' => $prior?->account_no ?? '',
                'To' => $account->account_no ?? '',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{0: string, 1: string}
     */
    private function formatAssignmentChange(array $details): array
    {
        $fromParts = [];
        $toParts = [];
        $fromIds = (array) ($details['from'] ?? []);
        $toIds = (array) ($details['to'] ?? []);

        foreach (array_keys(self::FIELD_LABELS) as $field) {
            if (! array_key_exists($field, $fromIds) && ! array_key_exists($field, $toIds)) {
                continue;
            }
            $label = self::FIELD_LABELS[$field];
            $fromParts[] = $label.': '.$this->resolveName($field, $fromIds[$field] ?? null);
            $toParts[] = $label.': '.$this->resolveName($field, $toIds[$field] ?? null);
        }

        return [implode('; ', $fromParts), implode('; ', $toParts)];
    }

    private function resolveName(string $field, mixed $id): string
    {
        if ($id === null || $id === '') {
            return '';
        }

        return match ($field) {
            'org_unit_id' => (string) (OrgUnit::query()->find($id)?->name ?? $id),
            'job_id' => (string) (Job::query()->find($id)?->name ?? $id),
            'grade_id' => (string) (Grade::query()->find($id)?->name ?? $id),
            'location_id' => (string) (Location::query()->find($id)?->name ?? $id),
            'manager_employment_id' => (string) (DB::table('employments')->where('id', $id)->value('employee_no') ?? $id),
            default => (string) $id,
        };
    }

    private function changeLabel(EventType $type): string
    {
        return match ($type) {
            EventType::ASSIGNMENT_CHANGE => 'Assignment change',
            EventType::CONFIRMATION => 'Confirmation',
            EventType::TYPE_CHANGE => 'Type change',
            EventType::SUSPENSION => 'Suspension',
            EventType::REINSTATEMENT => 'Reinstatement',
            default => $type->value,
        };
    }

    /** @return array{0: string, 1: string} */
    private function departmentSection(?OrgUnit $unit): array
    {
        if ($unit === null) {
            return ['', ''];
        }

        $unit->loadMissing('parent');

        if ($unit->type === OrgUnitType::DEPARTMENT) {
            return [$unit->name, ''];
        }

        if ($unit->type === OrgUnitType::SECTION) {
            return [$unit->parent?->name ?? '', $unit->name];
        }

        // SUBSECTION: section = parent, department = grandparent
        $section = $unit->parent;
        $section?->loadMissing('parent');

        return [$section?->parent?->name ?? '', $section?->name ?? $unit->name];
    }
}
