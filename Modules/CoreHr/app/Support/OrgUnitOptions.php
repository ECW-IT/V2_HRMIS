<?php

namespace Modules\CoreHr\Support;

use Modules\Organisation\Models\OrgUnit;

/** Flat, indented list of active org units for the "tree picker" selects. */
final class OrgUnitOptions
{
    /**
     * @param  list<int>|int|null  $companyIds  null = every company the user may see
     * @return list<array{id: int, label: string, depth: int}>
     */
    public static function forCompany(array|int|null $companyIds = null): array
    {
        $units = OrgUnit::query()
            ->where('is_active', true)
            ->when($companyIds !== null, fn ($q) => $q->whereIn('company_id', (array) $companyIds))
            ->orderBy('code')
            ->get();

        $byParent = $units->groupBy(fn (OrgUnit $unit) => $unit->parent_id ?? 0);
        $ids = $units->pluck('id')->all();
        $rows = [];

        $walk = function (int $parentId, int $depth) use (&$walk, &$rows, $byParent): void {
            foreach ($byParent->get($parentId, collect()) as $unit) {
                $rows[] = [
                    'id' => (int) $unit->id,
                    'label' => str_repeat('— ', $depth).$unit->name,
                    'depth' => $depth,
                ];
                $walk((int) $unit->id, $depth + 1);
            }
        };

        $walk(0, 0);

        // Units whose parent is inactive or hidden still need to be selectable.
        $listed = array_column($rows, 'id');
        foreach ($units as $unit) {
            if ($unit->parent_id !== null && ! in_array((int) $unit->parent_id, $ids, true) && ! in_array((int) $unit->id, $listed, true)) {
                $rows[] = ['id' => (int) $unit->id, 'label' => $unit->name, 'depth' => 0];
                $walk((int) $unit->id, 1);
            }
        }

        return $rows;
    }
}
