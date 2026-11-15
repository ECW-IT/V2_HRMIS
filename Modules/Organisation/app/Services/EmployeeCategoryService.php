<?php

namespace Modules\Organisation\Services;

use Modules\Organisation\Exceptions\DuplicateName;
use Modules\Organisation\Models\EmployeeCategory;

final class EmployeeCategoryService
{
    /** @param  array{code: string, name: string}  $data */
    public function create(array $data): EmployeeCategory
    {
        $nameKey = self::nameKey($data['name']);
        $this->assertUniqueNameKey($nameKey);

        return EmployeeCategory::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'name_key' => $nameKey,
            'is_active' => true,
        ]);
    }

    /** @param  array{code?: string, name?: string, is_active?: bool}  $data */
    public function update(EmployeeCategory $category, array $data): EmployeeCategory
    {
        $name = $data['name'] ?? $category->name;
        $nameKey = self::nameKey($name);

        if ($nameKey !== $category->name_key) {
            $this->assertUniqueNameKey($nameKey, $category->id);
        }

        $category->update([
            'code' => $data['code'] ?? $category->code,
            'name' => $name,
            'name_key' => $nameKey,
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
}
