<?php

namespace Modules\Organisation\Services;

use Illuminate\Support\Facades\DB;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Models\Assignment;
use Modules\Organisation\Enums\OrgUnitType;
use Modules\Organisation\Exceptions\DuplicateName;
use Modules\Organisation\Exceptions\InvalidOrgMove;
use Modules\Organisation\Exceptions\OrgUnitInUse;
use Modules\Organisation\Models\OrgUnit;

final class OrgUnitService
{
    /**
     * @param  array{company_id: int, parent_id?: ?int, code: string, name: string, type: OrgUnitType|string}  $data
     */
    public function create(array $data): OrgUnit
    {
        return DB::transaction(function () use ($data) {
            $type = $data['type'] instanceof OrgUnitType
                ? $data['type']
                : OrgUnitType::from((string) $data['type']);

            $parent = isset($data['parent_id']) && $data['parent_id'] !== null
                ? OrgUnit::query()->findOrFail($data['parent_id'])
                : null;

            $this->assertHierarchy((int) $data['company_id'], $type, $parent);
            $this->assertUniqueCode((int) $data['company_id'], (string) $data['code']);

            return OrgUnit::query()->create([
                'company_id' => $data['company_id'],
                'parent_id' => $parent?->id,
                'code' => $data['code'],
                'name' => $data['name'],
                'type' => $type,
                'is_active' => true,
            ]);
        });
    }

    /**
     * @param  array{name?: string, code?: string, type?: OrgUnitType|string}  $data
     */
    public function update(OrgUnit $unit, array $data): OrgUnit
    {
        return DB::transaction(function () use ($unit, $data) {
            $type = array_key_exists('type', $data)
                ? ($data['type'] instanceof OrgUnitType ? $data['type'] : OrgUnitType::from((string) $data['type']))
                : $unit->type;

            $code = array_key_exists('code', $data) ? (string) $data['code'] : $unit->code;
            $parent = $unit->parent_id !== null
                ? OrgUnit::query()->find($unit->parent_id)
                : null;

            $this->assertHierarchy((int) $unit->company_id, $type, $parent);

            if ($code !== $unit->code) {
                $this->assertUniqueCode((int) $unit->company_id, $code, $unit->id);
            }

            $unit->update([
                'name' => $data['name'] ?? $unit->name,
                'code' => $code,
                'type' => $type,
            ]);

            return $unit->fresh();
        });
    }

    public function move(OrgUnit $unit, ?OrgUnit $newParent): OrgUnit
    {
        return DB::transaction(function () use ($unit, $newParent) {
            if ($newParent !== null && $newParent->id === $unit->id) {
                throw new InvalidOrgMove('A unit cannot become its own parent.');
            }

            if ($newParent !== null && in_array($newParent->id, $this->descendantIds($unit), true)) {
                throw new InvalidOrgMove('A unit cannot move under one of its descendants.');
            }

            $this->assertHierarchy((int) $unit->company_id, $unit->type, $newParent);

            $unit->update(['parent_id' => $newParent?->id]);

            return $unit->fresh();
        });
    }

    public function deactivate(OrgUnit $unit): void
    {
        $hasActiveChild = OrgUnit::query()
            ->where('parent_id', $unit->id)
            ->where('is_active', true)
            ->exists();

        if ($hasActiveChild) {
            throw new OrgUnitInUse('Deactivate the units beneath it first.');
        }

        if ($this->hasCurrentAssignments($this->descendantIds($unit))) {
            throw new OrgUnitInUse('Employees are still assigned here.');
        }

        $unit->update(['is_active' => false]);
    }

    /** @return list<int> */
    public function descendantIds(OrgUnit $unit): array
    {
        $ids = [(int) $unit->id];
        $frontier = [(int) $unit->id];

        while ($frontier !== []) {
            $children = OrgUnit::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $frontier = [];

            foreach ($children as $childId) {
                if (! in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $frontier[] = $childId;
                }
            }
        }

        return $ids;
    }

    /** @param  list<int>  $orgUnitIds */
    public function hasCurrentAssignments(array $orgUnitIds): bool
    {
        if ($orgUnitIds === []) {
            return false;
        }

        return Assignment::query()
            ->current()
            ->whereIn('org_unit_id', $orgUnitIds)
            ->whereHas('employment', fn ($q) => $q->where('status', '!=', EmploymentStatus::EXITED))
            ->exists();
    }

    private function assertUniqueCode(int $companyId, string $code, ?int $ignoreId = null): void
    {
        $exists = OrgUnit::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw new DuplicateName('An organisation unit with this code already exists in the company.');
        }
    }

    private function assertHierarchy(int $companyId, OrgUnitType $type, ?OrgUnit $parent): void
    {
        if ($parent !== null && (int) $parent->company_id !== $companyId) {
            throw new InvalidOrgMove('Parent must be in the same company.');
        }

        if ($type === OrgUnitType::DEPARTMENT) {
            if ($parent !== null && $parent->type !== OrgUnitType::DEPARTMENT) {
                throw new InvalidOrgMove('A department parent must be a department.');
            }

            return;
        }

        if ($type === OrgUnitType::SECTION) {
            if ($parent === null || $parent->type !== OrgUnitType::DEPARTMENT) {
                throw new InvalidOrgMove('A section must sit under a department.');
            }

            return;
        }

        if ($type === OrgUnitType::SUBSECTION) {
            if ($parent === null || $parent->type !== OrgUnitType::SECTION) {
                throw new InvalidOrgMove('A subsection must sit under a section.');
            }
        }
    }
}
