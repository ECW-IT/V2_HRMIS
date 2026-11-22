<?php

namespace Modules\Organisation\Services;

use Illuminate\Support\Facades\DB;
use Modules\Organisation\Exceptions\DuplicateName;
use Modules\Organisation\Models\Designation;

final class DesignationService
{
    /**
     * @param  array{code: string, name: string}  $data
     */
    public function create(array $data): Designation
    {
        return DB::transaction(function () use ($data) {
            $nameKey = self::nameKey($data['name']);
            $this->assertUniqueNameKey($nameKey);

            return Designation::query()->create([
                'code' => $data['code'],
                'name' => $data['name'],
                'name_key' => $nameKey,
                'is_active' => true,
            ]);
        });
    }

    /**
     * @param  array{code?: string, name?: string, is_active?: bool}  $data
     */
    public function update(Designation $designation, array $data): Designation
    {
        return DB::transaction(function () use ($designation, $data) {
            $name = $data['name'] ?? $designation->name;
            $nameKey = self::nameKey($name);

            if ($nameKey !== $designation->name_key) {
                $this->assertUniqueNameKey($nameKey, $designation->id);
            }

            $designation->update([
                'code' => $data['code'] ?? $designation->code,
                'name' => $name,
                'name_key' => $nameKey,
                'is_active' => $data['is_active'] ?? $designation->is_active,
            ]);

            return $designation->fresh();
        });
    }

    public static function nameKey(string $name): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($name)) ?? '';

        return mb_strtolower($collapsed);
    }

    private function assertUniqueNameKey(string $nameKey, ?int $ignoreId = null): void
    {
        $exists = Designation::query()
            ->where('name_key', $nameKey)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw new DuplicateName('A designation with this name already exists.');
        }
    }
}
