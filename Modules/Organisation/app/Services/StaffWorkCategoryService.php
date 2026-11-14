<?php

namespace Modules\Organisation\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\StaffWorkCategory;

final class StaffWorkCategoryService
{
    /** @param  array{code: string, name: string, is_active?: bool}  $data */
    public function create(array $data): StaffWorkCategory
    {
        $this->assertUniqueCode($data['code']);

        return StaffWorkCategory::query()->create([
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }

    /** @param  array{code?: string, name?: string, is_active?: bool}  $data */
    public function update(StaffWorkCategory $category, array $data): StaffWorkCategory
    {
        if (array_key_exists('code', $data)) {
            $code = strtoupper(trim((string) $data['code']));
            $this->assertUniqueCode($code, $category->id);
            $category->code = $code;
        }

        if (array_key_exists('name', $data)) {
            $category->name = trim((string) $data['name']);
        }

        if (array_key_exists('is_active', $data)) {
            $category->is_active = (bool) $data['is_active'];
        }

        $category->save();

        return $category->fresh();
    }

    public function deactivate(StaffWorkCategory $category): StaffWorkCategory
    {
        $category->update(['is_active' => false]);

        return $category->fresh();
    }

    public function assign(Employment $employment, ?StaffWorkCategory $category): Employment
    {
        if ($category !== null && ! $category->is_active) {
            throw ValidationException::withMessages([
                'staff_work_category_id' => 'That office / shop category is inactive.',
            ]);
        }

        return DB::transaction(function () use ($employment, $category) {
            $employment->update([
                'staff_work_category_id' => $category?->id,
            ]);

            return $employment->fresh(['staffWorkCategory']);
        });
    }

    private function assertUniqueCode(string $code, ?int $ignoreId = null): void
    {
        $query = StaffWorkCategory::query()->where('code', strtoupper(trim($code)));
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'code' => 'That code is already in use.',
            ]);
        }
    }
}
