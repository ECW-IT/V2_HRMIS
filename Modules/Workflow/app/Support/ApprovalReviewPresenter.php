<?php

namespace Modules\Workflow\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Designation;
use Modules\Organisation\Models\Grade;
use Modules\Organisation\Models\Location;
use Modules\Organisation\Models\OrgUnit;
use Modules\Workflow\Models\ApprovalRequest;

final class ApprovalReviewPresenter
{
    private const OPERATIONS = [
        'UPDATE_PERSON' => 'Personal details',
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
    ];

    /**
     * @return list<array{field: string, current: string, requested: string, changed: bool}>
     */
    public static function rows(ApprovalRequest $request): array
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
            'grade_id' => Grade::query()->find($id)?->name ?? (string) $id,
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
