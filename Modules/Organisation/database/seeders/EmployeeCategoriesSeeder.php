<?php

namespace Modules\Organisation\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Services\EmployeeCategoryService;

class EmployeeCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('docs/mapping/employee_categories.csv');
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

        while (($cols = fgetcsv($handle)) !== false) {
            if ($cols === [null] || $cols === false) {
                continue;
            }

            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = trim((string) ($cols[$i] ?? ''));
            }

            if (($row['code'] ?? '') === '' || ($row['name'] ?? '') === '' || ($row['level'] ?? '') === '') {
                continue;
            }

            EmployeeCategory::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'name_key' => EmployeeCategoryService::nameKey($row['name']),
                    'level' => (int) $row['level'],
                    'is_active' => true,
                ],
            );
        }

        fclose($handle);
    }
}
