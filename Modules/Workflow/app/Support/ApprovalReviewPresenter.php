<?php

namespace Modules\Workflow\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Modules\CoreHr\Models\Bank;
use Modules\CoreHr\Models\BankBranch;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\PersonContact;
use Modules\CoreHr\Models\PersonDependent;
use Modules\CoreHr\Models\PersonEmergencyContact;
use Modules\CoreHr\Models\PersonExperience;
use Modules\CoreHr\Models\PersonQualification;
use Modules\CoreHr\Services\BankAccountService;
use Modules\Organisation\Models\Designation;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Models\Location;
use Modules\Organisation\Models\OrgUnit;
use Modules\Organisation\Models\StaffWorkCategory;
use Modules\Workflow\Models\ApprovalRequest;

final class ApprovalReviewPresenter
{
    private const OPERATIONS = [
        'UPDATE_PERSON' => 'Personal details',
        'UPSERT_CONTACT' => 'Contact',
        'REMOVE_CONTACT' => 'Remove contact',
        'UPSERT_CHILD' => 'Personal record',
        'REMOVE_CHILD' => 'Remove personal record',
        'UPDATE_CLASSIFICATION' => 'Employment classification',
        'UPDATE_STAFF_CATEGORY' => 'Office / shop category',
        'UPDATE_MODULE_FLAGS' => 'Module flags',
        'SET_BANK' => 'Bank account',
        'CHANGE_ASSIGNMENT' => 'Change assignment',
        'CONFIRM' => 'Confirm employment',
        'SUSPEND' => 'Suspend',
        'REINSTATE' => 'Reinstate',
        'EXIT' => 'Exit',
        'SISTER_TRANSFER' => 'Sister company transfer',
    ];

    private const FIELD_LABELS = [
        'calling_name' => 'Calling name',
        'full_name' => 'Full name',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'middle_name' => 'Middle name',
        'name_with_initials' => 'Name with initials',
        'title' => 'Title',
        'marital_status' => 'Marital status',
        'nationality' => 'Nationality',
        'driving_licence_no' => 'Driving licence no.',
        'landline_no' => 'Landline no.',
        'office_no' => 'Office no.',
        'region' => 'Religion',
        'arrive_work_from' => 'Arrive work from',
        'province' => 'Province',
        'electorate_district' => 'Electorate district',
        'gn_division' => 'GN division',
        'distance_km' => 'Distance (km)',
        'addresses' => 'Addresses',
        'org_unit_id' => 'Department / section',
        'designation_id' => 'Designation',
        'grade_id' => 'Grade',
        'location_id' => 'Location',
        'manager_employment_id' => 'Reports to',
        'reason' => 'Reason',
        'on' => 'Effective on',
        'from' => 'Effective from',
        'last_day' => 'Last working day',
        'remarks' => 'Remarks',
        'to_company_id' => 'Target company',
        'name' => 'Name',
        'employee_category_id' => 'Employee category',
        'staff_work_category_id' => 'Office / shop category',
        'hrmis_active' => 'HRMIS active',
        'attendance_active' => 'Attendance active',
        'payroll_active' => 'Payroll active',
        'is_cash' => 'Paid in cash',
        'bank_id' => 'Bank',
        'bank_branch_id' => 'Branch',
        'account_no' => 'Account no.',
        'account_name' => 'Account name',
        'effective_from' => 'Effective from',
        'is_primary' => 'Primary contact',
        'value' => 'Contact',
        'phone' => 'Phone',
        'relationship' => 'Relationship',
        'date_of_birth' => 'Date of birth',
        'institute' => 'Institute',
        'year' => 'Year',
        'employer' => 'Employer',
        'job_title' => 'Job title',
        'from_date' => 'From',
        'to_date' => 'To',
        'address' => 'Address',
    ];

    /**
     * @return list<array{field: string, current: string, requested: string, changed: bool}>
     */
    public static function rows(ApprovalRequest $request, bool $revealBankAccount = true): array
    {
        $payload = $request->payload ?? [];
        if ($payload === []) {
            return [];
        }

        $subject = null;
        if ($request->subject_type && $request->subject_id) {
            $subject = $request->subject_type::query()->find($request->subject_id);
        }

        $rows = [];

        if (isset($payload['operation']) && is_string($payload['operation'])) {
            $rows[] = self::row(
                'Operation',
                '—',
                self::OPERATIONS[$payload['operation']] ?? self::label($payload['operation']),
                true,
            );
        }

        foreach ($payload as $key => $value) {
            if ($key === 'operation') {
                continue;
            }

            if ($key === 'person' && is_array($value) && $subject instanceof Employment) {
                array_push($rows, ...self::personDiffRows($subject, $value));

                continue;
            }

            if ($key === 'changes' && is_array($value) && $subject instanceof Employment) {
                $from = is_string($payload['from'] ?? null) ? $payload['from'] : null;
                array_push($rows, ...self::assignmentDiffRows($subject, $value, $from));

                continue;
            }

            if ($key === 'kind') {
                continue;
            }

            if ($key === 'contact' && is_array($value) && $subject instanceof Employment) {
                array_push($rows, ...self::contactRows($subject, $value));

                continue;
            }

            if ($key === 'contact_id' && $subject instanceof Employment) {
                array_push($rows, ...self::removeContactRows($subject, (int) $value));

                continue;
            }

            if ($key === 'child' && is_array($value) && $subject instanceof Employment) {
                $kind = is_string($payload['kind'] ?? null) ? $payload['kind'] : '';
                array_push($rows, ...self::childRows($subject, $kind, $value));

                continue;
            }

            if ($key === 'child_id' && $subject instanceof Employment) {
                $kind = is_string($payload['kind'] ?? null) ? $payload['kind'] : '';
                array_push($rows, ...self::removeChildRows($subject, $kind, (int) $value));

                continue;
            }

            if ($key === 'classification' && is_array($value) && $subject instanceof Employment) {
                array_push($rows, ...self::classificationRows($subject, $value));

                continue;
            }

            if ($key === 'flags' && is_array($value) && $subject instanceof Employment) {
                array_push($rows, ...self::flagRows($subject, $value));

                continue;
            }

            if ($key === 'staff_work_category_id' && $subject instanceof Employment) {
                array_push($rows, ...self::staffCategoryRows($subject, $value));

                continue;
            }

            if ($key === 'account' && is_array($value) && $subject instanceof Employment) {
                array_push($rows, ...self::bankRows($subject, $value, $revealBankAccount));

                continue;
            }

            if ($key === 'assignment' && is_array($value)) {
                foreach ($value as $field => $requested) {
                    $rows[] = self::row(
                        self::FIELD_LABELS[$field] ?? self::label((string) $field),
                        '—',
                        self::formatAssignmentValue((string) $field, $requested),
                        true,
                    );
                }

                continue;
            }

            if ($key === 'new_employment' && is_array($value)) {
                if ($value === []) {
                    continue;
                }
                $rows[] = self::row('New employment', '—', count($value).' fields', true);

                continue;
            }

            if (is_array($value)) {
                $rows[] = self::row(
                    self::FIELD_LABELS[$key] ?? self::label((string) $key),
                    '—',
                    count($value).' fields',
                    true,
                );

                continue;
            }

            $current = '—';
            if ($subject instanceof Model && $subject->getAttribute($key) !== null) {
                $current = self::display($subject->getAttribute($key));
            }

            $requested = self::display($value);
            $rows[] = self::row(
                self::FIELD_LABELS[$key] ?? self::label((string) $key),
                $current,
                $requested,
                $current !== $requested,
            );
        }

        return $rows;
    }

    /**
     * @return array{employee: string, hidden: ?string, changes: list<array{field: string, old: string, new: string}>}
     */
    public static function listSummary(ApprovalRequest $request, bool $revealBankAccount, bool $hideCompensation): array
    {
        if ($hideCompensation) {
            return [
                'employee' => self::employeeName($request),
                'hidden' => 'Compensation change (hidden)',
                'changes' => [],
            ];
        }

        $data = [];
        $metadata = [];
        foreach (self::rows($request, $revealBankAccount) as $row) {
            if ($row['field'] === 'Operation' || ! $row['changed']) {
                continue;
            }

            $change = [
                'field' => $row['field'],
                'old' => $row['current'],
                'new' => $row['requested'],
            ];

            if (self::isCannedReason($change)) {
                continue;
            }

            if (self::isRequestMetadata($change['field'])) {
                $metadata[] = $change;
            } else {
                $data[] = $change;
            }
        }

        return [
            'employee' => self::employeeName($request),
            'hidden' => null,
            'changes' => $data !== [] ? $data : $metadata,
        ];
    }

    /** @param  array{field: string, old: string, new: string}  $change */
    private static function isCannedReason(array $change): bool
    {
        return $change['field'] === 'Reason' && in_array($change['new'], [
            'Personal details update',
            'Confirmation',
            'Suspension',
            'Reinstatement',
            'Transfer to sister company',
        ], true);
    }

    private static function isRequestMetadata(string $field): bool
    {
        return in_array($field, [
            'Reason',
            'Remarks',
            'Effective from',
            'Effective on',
            'Last working day',
            'Change reason',
        ], true);
    }

    private static function employeeName(ApprovalRequest $request): string
    {
        $employment = null;
        if ($request->subject_type === Employment::class && $request->subject_id) {
            $employment = Employment::query()->with('person')->find($request->subject_id);
        } elseif ($request->employment_id) {
            $employment = Employment::query()->with('person')->find($request->employment_id);
        }

        $name = $employment?->person?->name_with_initials;

        return is_string($name) && $name !== '' ? $name : '—';
    }

    /**
     * @param  array<string, mixed>  $requested
     * @return list<array{field: string, current: string, requested: string, changed: bool}>
     */
    private static function personDiffRows(Employment $employment, array $requested): array
    {
        $person = $employment->person()->with('addresses')->first();
        if ($person === null) {
            return [];
        }

        $rows = [];

        foreach ($requested as $field => $value) {
            if ($field === 'addresses' && is_array($value)) {
                $currentAddresses = $person->addresses
                    ->map(fn ($a) => trim(($a->line1 ?? '').', '.($a->city ?? ''), ' ,'))
                    ->filter()
                    ->implode('; ');
                $requestedAddresses = collect($value)
                    ->map(fn ($a) => trim(($a['line1'] ?? '').', '.($a['city'] ?? ''), ' ,'))
                    ->filter()
                    ->implode('; ');
                $currentDisplay = $currentAddresses !== '' ? $currentAddresses : '—';
                $requestedDisplay = $requestedAddresses !== '' ? $requestedAddresses : '—';
                if ($currentDisplay === $requestedDisplay) {
                    continue;
                }
                $rows[] = self::row('Addresses', $currentDisplay, $requestedDisplay, true);

                continue;
            }

            $currentRaw = $person->getAttribute($field);
            $currentDisplay = self::display($currentRaw);
            $requestedDisplay = self::display($value);
            if (self::normaliseComparable($currentRaw) === self::normaliseComparable($value)) {
                continue;
            }

            $rows[] = self::row(
                self::FIELD_LABELS[$field] ?? self::label((string) $field),
                $currentDisplay,
                $requestedDisplay,
                true,
            );
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return list<array{field: string, current: string, requested: string, changed: bool}>
     */
    private static function contactRows(Employment $employment, array $contact): array
    {
        $current = null;
        if (isset($contact['id']) && $contact['id'] !== null && $contact['id'] !== '') {
            $current = PersonContact::query()
                ->where('person_id', $employment->person_id)
                ->find((int) $contact['id']);
        }

        $rows = [];
        foreach (['type' => 'Contact type', 'value' => 'Contact', 'is_primary' => 'Primary contact'] as $field => $label) {
            if (! array_key_exists($field, $contact)) {
                continue;
            }

            $currentRaw = $current?->getAttribute($field);
            $requested = $contact[$field];
            if ($current !== null && self::normaliseComparable($currentRaw) === self::normaliseComparable($requested)) {
                continue;
            }

            $rows[] = self::row($label, $current === null ? '—' : self::display($currentRaw), self::display($requested), true);
        }

        return $rows;
    }

    /**
     * @return list<array{field: string, current: string, requested: string, changed: bool}>
     */
    private static function removeContactRows(Employment $employment, int $contactId): array
    {
        $contact = PersonContact::query()
            ->where('person_id', $employment->person_id)
            ->find($contactId);
        $current = $contact === null
            ? '—'
            : trim(self::display($contact->type).' '.$contact->value);

        return [self::row('Contact', $current, 'Removed', true)];
    }

    /**
     * @param  array<string, mixed>  $child
     * @return list<array{field: string, current: string, requested: string, changed: bool}>
     */
    private static function childRows(Employment $employment, string $kind, array $child): array
    {
        $current = null;
        $class = self::childClass($kind);
        if ($class !== null && isset($child['id']) && $child['id'] !== null && $child['id'] !== '') {
            $current = $class::query()
                ->where('person_id', $employment->person_id)
                ->find((int) $child['id']);
        }

        $rows = [];
        foreach ($child as $field => $requested) {
            if ($field === 'id') {
                continue;
            }

            $currentRaw = $current?->getAttribute($field);
            if ($current !== null && self::normaliseComparable($currentRaw) === self::normaliseComparable($requested)) {
                continue;
            }

            $rows[] = self::row(
                self::FIELD_LABELS[$field] ?? self::label((string) $field),
                $current === null ? '—' : self::display($currentRaw),
                self::display($requested),
                true,
            );
        }

        return $rows;
    }

    /**
     * @return list<array{field: string, current: string, requested: string, changed: bool}>
     */
    private static function removeChildRows(Employment $employment, string $kind, int $id): array
    {
        $class = self::childClass($kind);
        $current = $class === null
            ? null
            : $class::query()->where('person_id', $employment->person_id)->find($id);
        $summary = '—';
        if ($current !== null) {
            $summary = (string) ($current->name ?? $current->title ?? $current->employer ?? '—');
        }

        return [self::row(self::childLabel($kind), $summary, 'Removed', true)];
    }

    /**
     * @param  array<string, mixed>  $classification
     * @return list<array{field: string, current: string, requested: string, changed: bool}>
     */
    private static function classificationRows(Employment $employment, array $classification): array
    {
        $rows = [];
        if (array_key_exists('type', $classification)) {
            $currentRaw = $employment->type instanceof \BackedEnum ? $employment->type->value : (string) $employment->type;
            $requestedRaw = (string) $classification['type'];
            if ($currentRaw !== $requestedRaw) {
                $rows[] = self::row('Type', self::label($currentRaw), self::label($requestedRaw), true);
            }
        }

        if (array_key_exists('employee_category_id', $classification)) {
            $currentName = $employment->employee_category_id === null
                ? '—'
                : (EmployeeCategory::query()->find($employment->employee_category_id)?->name ?? (string) $employment->employee_category_id);
            $requestedId = $classification['employee_category_id'];
            $requestedName = $requestedId === null || $requestedId === ''
                ? '—'
                : (EmployeeCategory::query()->find((int) $requestedId)?->name ?? (string) $requestedId);
            if ($currentName !== $requestedName) {
                $rows[] = self::row('Employee category', $currentName, $requestedName, true);
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $flags
     * @return list<array{field: string, current: string, requested: string, changed: bool}>
     */
    private static function flagRows(Employment $employment, array $flags): array
    {
        $rows = [];
        foreach ($flags as $field => $requested) {
            $currentRaw = $employment->getAttribute($field);
            $rows[] = self::row(
                self::FIELD_LABELS[$field] ?? self::label((string) $field),
                self::display($currentRaw),
                self::display($requested),
                self::normaliseComparable($currentRaw) !== self::normaliseComparable($requested),
            );
        }

        return $rows;
    }

    /**
     * @return list<array{field: string, current: string, requested: string, changed: bool}>
     */
    private static function staffCategoryRows(Employment $employment, mixed $requestedId): array
    {
        $employment->loadMissing('staffWorkCategory');
        $current = $employment->staffWorkCategory?->name ?? '—';
        $requested = $requestedId === null || $requestedId === ''
            ? '—'
            : (StaffWorkCategory::query()->find((int) $requestedId)?->name ?? (string) $requestedId);

        return [self::row('Office / shop category', $current, $requested, $current !== $requested)];
    }

    /**
     * @param  array<string, mixed>  $account
     * @return list<array{field: string, current: string, requested: string, changed: bool}>
     */
    private static function bankRows(Employment $employment, array $account, bool $revealAccount): array
    {
        $on = is_string($account['effective_from'] ?? null) ? $account['effective_from'] : null;
        $current = $on === null ? null : app(BankAccountService::class)->on($employment, $on);

        $fields = ['effective_from', 'is_cash', 'bank_id', 'bank_branch_id', 'account_no', 'account_name'];
        $rows = [];
        foreach ($fields as $field) {
            if (! array_key_exists($field, $account) && $current === null) {
                continue;
            }

            $currentRaw = $current?->getAttribute($field);
            $requested = $account[$field] ?? null;
            $changed = self::normaliseComparable($currentRaw) !== self::normaliseComparable($requested);
            if (! $changed && $current !== null) {
                continue;
            }

            $currentDisplay = self::bankFieldDisplay($field, $currentRaw, $revealAccount);
            $requestedDisplay = self::bankFieldDisplay($field, $requested, $revealAccount);
            $rows[] = self::row(
                self::FIELD_LABELS[$field] ?? self::label($field),
                $current === null ? '—' : $currentDisplay,
                $requestedDisplay,
                $changed || $current === null,
            );
        }

        return $rows;
    }

    private static function bankFieldDisplay(string $field, mixed $value, bool $revealAccount): string
    {
        if ($field === 'account_no' && ! $revealAccount && $value !== null && $value !== '') {
            return 'Hidden';
        }

        if ($field === 'bank_id' && $value !== null && $value !== '') {
            return Bank::query()->find((int) $value)?->name ?? (string) $value;
        }

        if ($field === 'bank_branch_id' && $value !== null && $value !== '') {
            return BankBranch::query()->find((int) $value)?->name ?? (string) $value;
        }

        return self::display($value);
    }

    /** @return class-string<Model>|null */
    private static function childClass(string $kind): ?string
    {
        return match ($kind) {
            'emergency' => PersonEmergencyContact::class,
            'dependent' => PersonDependent::class,
            'qualification' => PersonQualification::class,
            'experience' => PersonExperience::class,
            default => null,
        };
    }

    private static function childLabel(string $kind): string
    {
        return match ($kind) {
            'emergency' => 'Emergency contact',
            'dependent' => 'Dependent',
            'qualification' => 'Qualification',
            'experience' => 'Experience',
            default => 'Personal record',
        };
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return list<array{field: string, current: string, requested: string, changed: bool}>
     */
    private static function assignmentDiffRows(Employment $employment, array $changes, ?string $from): array
    {
        $assignment = $from !== null
            ? $employment->assignmentOn($from)
            : $employment->currentAssignment();

        $rows = [];
        foreach ($changes as $field => $requested) {
            $currentRaw = $assignment?->getAttribute($field);
            $currentDisplay = self::formatAssignmentValue((string) $field, $currentRaw);
            $requestedDisplay = self::formatAssignmentValue((string) $field, $requested);
            $rows[] = self::row(
                self::FIELD_LABELS[$field] ?? self::label((string) $field),
                $currentDisplay,
                $requestedDisplay,
                self::normaliseComparable($currentRaw) !== self::normaliseComparable($requested),
            );
        }

        return $rows;
    }

    private static function formatAssignmentValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $id = is_numeric($value) ? (int) $value : null;
        if ($id === null) {
            return self::display($value);
        }

        return match ($field) {
            'org_unit_id' => OrgUnit::query()->find($id)?->name ?? (string) $id,
            'designation_id' => Designation::query()->find($id)?->name ?? (string) $id,
            'grade_id' => (string) $id,
            'location_id' => Location::query()->find($id)?->name ?? (string) $id,
            'manager_employment_id' => Employment::query()->with('person')->find($id)?->person?->name_with_initials
                ?? (string) $id,
            default => self::display($value),
        };
    }

    private static function display(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value instanceof \BackedEnum) {
            return self::label((string) $value->value);
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->toDateString();
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
            try {
                return CarbonImmutable::parse($value)->toDateString();
            } catch (\Throwable) {
                return $value;
            }
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return 'complex value';
    }

    private static function normaliseComparable(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->toDateString();
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
            try {
                return CarbonImmutable::parse($value)->toDateString();
            } catch (\Throwable) {
                return trim($value);
            }
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return md5(json_encode($value) ?: '');
    }

    private static function label(string $key): string
    {
        return ucfirst(strtolower(str_replace('_', ' ', $key)));
    }

    /** @return array{field: string, current: string, requested: string, changed: bool} */
    private static function row(string $field, string $current, string $requested, bool $changed): array
    {
        return [
            'field' => $field,
            'current' => $current,
            'requested' => $requested,
            'changed' => $changed,
        ];
    }
}
