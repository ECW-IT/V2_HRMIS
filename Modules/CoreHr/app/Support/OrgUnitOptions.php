<?php

namespace Modules\CoreHr\Support;

use Illuminate\Support\Collection;
use Modules\Organisation\Enums\OrgUnitType;
use Modules\Organisation\Models\OrgUnit;

/** Flat, indented list of active org units for the "tree picker" selects. */
final class OrgUnitOptions
{
    /**
     * @param  list<int>|int|null  $companyIds  null = every company the user may see
     * @return list<array{id: int, label: string, depth: int, type: string}>
     */
    public static function forCompany(array|int|null $companyIds = null): array
    {
        $units = self::activeUnits($companyIds);

        return self::walkTree($units, parentId: 0, startDepth: 0, includeRootsOutsideTree: true);
    }

    /**
     * Active departments for a company, for cascading hire pickers.
     *
     * @return list<array{id: int, label: string}>
     */
    public static function departments(array|int|null $companyIds = null): array
    {
        return OrgUnit::query()
            ->where('is_active', true)
            ->departments()
            ->when($companyIds !== null, fn ($q) => $q->whereIn('company_id', (array) $companyIds))
            ->orderBy('code')
            ->get(['id', 'name'])
            ->map(fn (OrgUnit $unit) => [
                'id' => (int) $unit->id,
                'label' => $unit->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Sections and subsections under a department (indented). Empty when the department has no children.
     *
     * @return list<array{id: int, label: string, depth: int, type: string}>
     */
    public static function underDepartment(int $departmentId, array|int|null $companyIds = null): array
    {
        $department = OrgUnit::query()
            ->where('is_active', true)
            ->departments()
            ->when($companyIds !== null, fn ($q) => $q->whereIn('company_id', (array) $companyIds))
            ->find($departmentId);

        if ($department === null) {
            return [];
        }

        $units = self::activeUnits($companyIds);
        $rows = self::walkTree($units, parentId: $departmentId, startDepth: 0, includeRootsOutsideTree: false);

        return array_values(array_filter(
            $rows,
            fn (array $row) => in_array($row['type'], [OrgUnitType::SECTION->value, OrgUnitType::SUBSECTION->value], true),
        ));
    }

    /**
     * @param  list<int>|int|null  $companyIds
     * @return Collection<int, OrgUnit>
     */
    private static function activeUnits(array|int|null $companyIds)
    {
        return OrgUnit::query()
            ->where('is_active', true)
            ->when($companyIds !== null, fn ($q) => $q->whereIn('company_id', (array) $companyIds))
            ->orderBy('code')
            ->get();
    }

    /**
     * @param  Collection<int, OrgUnit>  $units
     * @return list<array{id: int, label: string, depth: int, type: string}>
     */
    private static function walkTree($units, int $parentId, int $startDepth, bool $includeRootsOutsideTree): array
    {
        $byParent = $units->groupBy(fn (OrgUnit $unit) => $unit->parent_id ?? 0);
        $ids = $units->pluck('id')->all();
        $rows = [];

        $walk = function (int $pid, int $depth) use (&$walk, &$rows, $byParent): void {
            foreach ($byParent->get($pid, collect()) as $unit) {
                $rows[] = [
                    'id' => (int) $unit->id,
                    'label' => str_repeat('— ', $depth).$unit->name,
                    'depth' => $depth,
                    'type' => $unit->type instanceof \BackedEnum ? $unit->type->value : (string) $unit->type,
                ];
                $walk((int) $unit->id, $depth + 1);
            }
        };

        $walk($parentId, $startDepth);

        if ($includeRootsOutsideTree) {
            $listed = array_column($rows, 'id');
            foreach ($units as $unit) {
                if ($unit->parent_id !== null && ! in_array((int) $unit->parent_id, $ids, true) && ! in_array((int) $unit->id, $listed, true)) {
                    $rows[] = [
                        'id' => (int) $unit->id,
                        'label' => $unit->name,
                        'depth' => 0,
                        'type' => $unit->type instanceof \BackedEnum ? $unit->type->value : (string) $unit->type,
                    ];
                    $walk((int) $unit->id, 1);
                }
            }
        }

        return $rows;
    }
}
