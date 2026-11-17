<?php

namespace Modules\Organisation\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\OrgUnit;
use Modules\Organisation\Services\OrgUnitService;

class OrgUnitsSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('docs/mapping/org_units.csv');
        if (! is_file($path)) {
            return;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return;
        }

        $header = array_map(static fn ($h) => trim((string) $h), $header);
        $companies = Company::query()->get()->keyBy('code');
        $created = [];
        $units = app(OrgUnitService::class);

        while (($cols = fgetcsv($handle)) !== false) {
            if ($cols === [null] || $cols === false) {
                continue;
            }

            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = trim((string) ($cols[$i] ?? ''));
            }

            if (($row['company_code'] ?? '') === '' || ($row['code'] ?? '') === '') {
                continue;
            }

            $company = $companies->get($row['company_code']);
            if ($company === null) {
                continue;
            }

            $key = $row['company_code'].'|'.$row['code'];
            $existing = OrgUnit::query()
                ->where('company_id', $company->id)
                ->where('code', $row['code'])
                ->first();

            if ($existing !== null) {
                $created[$key] = $existing;

                continue;
            }

            $parentId = null;
            $parentCode = $row['parent_code'] ?? '';
            if ($parentCode !== '') {
                $parent = $created[$row['company_code'].'|'.$parentCode]
                    ?? OrgUnit::query()
                        ->where('company_id', $company->id)
                        ->where('code', $parentCode)
                        ->first();
                $parentId = $parent?->id;
            }

            $created[$key] = $units->create([
                'company_id' => $company->id,
                'parent_id' => $parentId,
                'code' => $row['code'],
                'name' => $row['name'],
                'type' => $row['type'],
            ]);
        }

        fclose($handle);
    }
}
