<?php

namespace Modules\Organisation\Services;

use Illuminate\Support\Facades\DB;
use Modules\Organisation\Exceptions\DuplicateLevel;
use Modules\Organisation\Exceptions\DuplicateName;
use Modules\Organisation\Exceptions\InvalidCategoryBreak;
use Modules\Organisation\Models\EmployeeCategory;

final class EmployeeCategoryService
{
    /** @param  array{code: string, name: string, level: int}  $data */
    public function create(array $data): EmployeeCategory
    {
        $nameKey = self::nameKey($data['name']);
        $this->assertUniqueNameKey($nameKey);
        $this->assertUniqueLevel((int) $data['level']);

        return DB::transaction(function () use ($data, $nameKey) {
            $category = EmployeeCategory::query()->create([
                'code' => $data['code'],
                'name' => $data['name'],
                'name_key' => $nameKey,
                'level' => (int) $data['level'],
                'is_active' => true,
                'early_ot' => (bool) ($data['early_ot'] ?? false),
                'evening_ot' => (bool) ($data['evening_ot'] ?? false),
                'late_deduction' => (bool) ($data['late_deduction'] ?? false),
            ]);

            if (array_key_exists('breaks', $data)) {
                $this->replaceBreaks($category, $data['breaks']);
            }

            return $category->fresh('breaks');
        });
    }

    /** @param  array{code?: string, name?: string, level?: int, is_active?: bool}  $data */
    public function update(EmployeeCategory $category, array $data): EmployeeCategory
    {
        $name = $data['name'] ?? $category->name;
        $nameKey = self::nameKey($name);

        if ($nameKey !== $category->name_key) {
            $this->assertUniqueNameKey($nameKey, $category->id);
        }

        $level = array_key_exists('level', $data) ? (int) $data['level'] : $category->level;
        if ($level !== $category->level) {
            $this->assertUniqueLevel($level, $category->id);
        }

        return DB::transaction(function () use ($category, $data, $name, $nameKey, $level) {
            $category->update([
                'code' => $data['code'] ?? $category->code,
                'name' => $name,
                'name_key' => $nameKey,
                'level' => $level,
                'is_active' => $data['is_active'] ?? $category->is_active,
                'early_ot' => array_key_exists('early_ot', $data) ? (bool) $data['early_ot'] : $category->early_ot,
                'evening_ot' => array_key_exists('evening_ot', $data) ? (bool) $data['evening_ot'] : $category->evening_ot,
                'late_deduction' => array_key_exists('late_deduction', $data) ? (bool) $data['late_deduction'] : $category->late_deduction,
            ]);

            if (array_key_exists('breaks', $data)) {
                $this->replaceBreaks($category, $data['breaks']);
            }

            return $category->fresh('breaks');
        });
    }

    /** @param  list<array{name?: string, start_time?: string, end_time?: string, is_paid?: bool}>  $breaks */
    private function replaceBreaks(EmployeeCategory $category, array $breaks): void
    {
        $category->breaks()->delete();

        foreach ($breaks as $break) {
            $name = trim((string) ($break['name'] ?? ''));
            $start = $this->asTime((string) ($break['start_time'] ?? ''));
            $end = $this->asTime((string) ($break['end_time'] ?? ''));

            if ($name === '' || $start === '' || $end === '') {
                throw new InvalidCategoryBreak('Each break needs a name, start, and end.');
            }

            if ($start >= $end) {
                throw new InvalidCategoryBreak('A break must end after it starts.');
            }

            $category->breaks()->create([
                'name' => $name,
                'start_time' => $start,
                'end_time' => $end,
                'is_paid' => (bool) ($break['is_paid'] ?? false),
            ]);
        }
    }

    private function asTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value.':00';
        }

        return $value;
    }

    public static function nameKey(string $name): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($name)) ?? '';

        return mb_strtolower($collapsed);
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

    private function assertUniqueLevel(int $level, ?int $ignoreId = null): void
    {
        $exists = EmployeeCategory::query()
            ->where('level', $level)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw new DuplicateLevel('An employee category with this level already exists.');
        }
    }
}
