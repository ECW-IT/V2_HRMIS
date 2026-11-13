<?php

namespace Modules\Organisation\Services;

use Illuminate\Support\Facades\DB;
use Modules\Organisation\Exceptions\DuplicateName;
use Modules\Organisation\Models\BreakType;

final class BreakTypeService
{
    /**
     * @param  array{name: string, is_paid?: bool, remarks?: ?string}  $data
     */
    public function create(array $data): BreakType
    {
        return DB::transaction(function () use ($data) {
            $this->assertUniqueName($data['name']);

            return BreakType::query()->create([
                'name' => $data['name'],
                'is_paid' => (bool) ($data['is_paid'] ?? false),
                'remarks' => $data['remarks'] ?? null,
                'is_active' => true,
            ]);
        });
    }

    /**
     * @param  array{name?: string, is_paid?: bool, remarks?: ?string, is_active?: bool}  $data
     */
    public function update(BreakType $type, array $data): BreakType
    {
        return DB::transaction(function () use ($type, $data) {
            $name = $data['name'] ?? $type->name;

            if (mb_strtolower(trim($name)) !== mb_strtolower(trim($type->name))) {
                $this->assertUniqueName($name, $type->id);
            }

            $type->update([
                'name' => $name,
                'is_paid' => array_key_exists('is_paid', $data) ? (bool) $data['is_paid'] : $type->is_paid,
                'remarks' => array_key_exists('remarks', $data) ? $data['remarks'] : $type->remarks,
                'is_active' => $data['is_active'] ?? $type->is_active,
            ]);

            return $type->fresh();
        });
    }

    private function assertUniqueName(string $name, ?int $ignoreId = null): void
    {
        $exists = BreakType::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw new DuplicateName('A break type with this name already exists.');
        }
    }
}
