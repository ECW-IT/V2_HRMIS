<?php

namespace Modules\Organisation\Services;

use Modules\Organisation\Exceptions\DuplicateLevel;
use Modules\Organisation\Exceptions\DuplicateName;
use Modules\Organisation\Models\EmployeeCategory;

final class EmployeeCategoryService
{
    /** @param  array{code: string, name: string, level: int}  $data */
    public function create(array $data): EmployeeCategory
    {
        $nameKey = self::nameKey($data['name']);
        $this->assertUniqueNameKey($nameKey);
        $this->assertUniqueLevel((int) $data['level']);

        return EmployeeCategory::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'name_key' => $nameKey,
            'level' => (int) $data['level'],
            'is_active' => true,
        ]);
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

        $category->update([
            'code' => $data['code'] ?? $category->code,
            'name' => $name,
            'name_key' => $nameKey,
            'level' => $level,
            'is_active' => $data['is_active'] ?? $category->is_active,
        ]);

        return $category->fresh();
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
