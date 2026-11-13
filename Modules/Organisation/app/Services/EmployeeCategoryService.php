<?php

namespace Modules\Organisation\Services;

use App\Support\Effective\EffectiveDating;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Organisation\Exceptions\DuplicateName;
use Modules\Organisation\Exceptions\DutyEffectiveFromRequired;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Models\EmployeeCategoryDutySchedule;

final class EmployeeCategoryService
{
    private const DUTY_FIELDS = [
        'on_duty_time',
        'off_duty_time',
        'morning_short_beginning',
        'morning_short_ending',
        'evening_short_beginning',
        'evening_short_ending',
        'halfday_beginning',
        'late_on',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): EmployeeCategory
    {
        return DB::transaction(function () use ($data) {
            $nameKey = self::nameKey($data['name']);
            $this->assertUniqueNameKey($nameKey);

            $category = EmployeeCategory::query()->create([
                ...$this->payload($data),
                'code' => $data['code'],
                'name' => $data['name'],
                'name_key' => $nameKey,
                'is_active' => true,
            ]);

            $this->syncBreaks($category, $data['breaks'] ?? []);

            $duty = $this->dutyPayload($data);
            $hasDuty = collect($duty)->contains(fn ($value) => $value !== null);
            $effectiveFrom = $hasDuty
                ? CarbonImmutable::now(config('app.timezone'))->toDateString()
                : '2000-01-01';

            EffectiveDating::insert(new EmployeeCategoryDutySchedule([
                'employee_category_id' => $category->id,
                'effective_from' => $effectiveFrom,
                ...$duty,
            ]));

            return $category->fresh(['breaks', 'dutySchedules']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EmployeeCategory $category, array $data): EmployeeCategory
    {
        return DB::transaction(function () use ($category, $data) {
            $name = $data['name'] ?? $category->name;
            $nameKey = self::nameKey($name);

            if ($nameKey !== $category->name_key) {
                $this->assertUniqueNameKey($nameKey, $category->id);
            }

            $dutyChanged = $this->dutyTimesChanged($category, $data);

            if ($dutyChanged) {
                $effectiveFrom = $data['duty_effective_from'] ?? null;
                if ($effectiveFrom === null || $effectiveFrom === '') {
                    throw new DutyEffectiveFromRequired('Enter the date these duty times take effect.');
                }
            }

            $category->update([
                ...$this->payload($data, $category),
                'code' => $data['code'] ?? $category->code,
                'name' => $name,
                'name_key' => $nameKey,
                'is_active' => $data['is_active'] ?? $category->is_active,
            ]);

            if (array_key_exists('breaks', $data)) {
                $this->syncBreaks($category, $data['breaks']);
            }

            if ($dutyChanged) {
                EffectiveDating::insert(new EmployeeCategoryDutySchedule([
                    'employee_category_id' => $category->id,
                    'effective_from' => $data['duty_effective_from'],
                    ...$this->dutyPayload($data, $category),
                ]));
            }

            return $category->fresh(['breaks', 'dutySchedules']);
        });
    }

    public static function nameKey(string $name): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($name)) ?? '';

        return mb_strtolower($collapsed);
    }

    /**
     * @param  list<array{break_type_id: int, start_time: string, end_time: string, duration_minutes: int}>  $breaks
     */
    private function syncBreaks(EmployeeCategory $category, array $breaks): void
    {
        $keepTypeIds = [];

        foreach ($breaks as $row) {
            $typeId = (int) $row['break_type_id'];
            $keepTypeIds[] = $typeId;

            $category->breaks()->updateOrCreate(
                ['break_type_id' => $typeId],
                [
                    'start_time' => $this->normalizeTime($row['start_time']),
                    'end_time' => $this->normalizeTime($row['end_time']),
                    'duration_minutes' => (int) $row['duration_minutes'],
                ],
            );
        }

        $query = $category->breaks();
        if ($keepTypeIds !== []) {
            $query->whereNotIn('break_type_id', $keepTypeIds);
        }
        $query->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dutyTimesChanged(EmployeeCategory $category, array $data): bool
    {
        foreach (self::DUTY_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $incoming = $this->normalizeTimeComparable($data[$field] ?? null);
            $current = $this->normalizeTimeComparable($category->{$field});

            if ($incoming !== $current) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, ?string>
     */
    private function dutyPayload(array $data, ?EmployeeCategory $existing = null): array
    {
        $payload = [];
        foreach (self::DUTY_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $this->normalizeTime($data[$field] ?? null);
            } elseif ($existing !== null) {
                $payload[$field] = $this->normalizeTime($existing->{$field});
            } else {
                $payload[$field] = null;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data, ?EmployeeCategory $existing = null): array
    {
        $fields = [
            'annual_leaves',
            'casual_leaves',
            'medical_leaves',
            'normal_ot_rate',
            'double_ot_rate',
            'no_pay_rate_per_hour',
            'no_pay_rate_per_day',
            'saturday_rate',
            'sunday_rate',
            'payroll_workdays',
            'payroll_workhours',
            'saturday_ot_mode',
            'custom_saturday_ot_type',
            'sunday_ot_mode',
            'custom_sunday_ot_type',
            ...self::DUTY_FIELDS,
        ];

        $payload = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = in_array($field, self::DUTY_FIELDS, true)
                    ? $this->normalizeTime($data[$field])
                    : $data[$field];
            } elseif ($existing === null) {
                $payload[$field] = match ($field) {
                    'payroll_workdays', 'payroll_workhours', 'saturday_ot_mode', 'sunday_ot_mode' => 0,
                    default => null,
                };
            }
        }

        return $payload;
    }

    private function normalizeTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = (string) $value;

        return strlen($string) === 5 ? $string.':00' : $string;
    }

    private function normalizeTimeComparable(mixed $value): ?string
    {
        $normalized = $this->normalizeTime($value);

        return $normalized === null ? null : substr($normalized, 0, 5);
    }

    private function assertUniqueNameKey(string $nameKey, ?int $ignoreId = null): void
    {
        $exists = EmployeeCategory::query()
            ->where('name_key', $nameKey)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw new DuplicateName('An employee category with this name already exists.');
        }
    }
}
