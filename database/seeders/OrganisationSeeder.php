<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Grade;
use Modules\Organisation\Models\Location;

class OrganisationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (require database_path('seeders/data/companies.php') as $row) {
            Company::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'employee_no_prefix' => $row['employee_no_prefix'],
                    'employee_no_padding' => $row['employee_no_padding'],
                    'next_employee_seq' => 1,
                    'is_active' => true,
                ],
            );
        }

        foreach (require database_path('seeders/data/locations.php') as $row) {
            Location::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'address' => $row['address'] ?? null,
                    'is_active' => true,
                ],
            );
        }

        foreach (require database_path('seeders/data/grades.php') as $row) {
            Grade::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'rank' => $row['rank'],
                    'is_active' => true,
                ],
            );
        }
    }
}
