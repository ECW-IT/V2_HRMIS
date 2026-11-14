<?php

namespace Modules\Organisation\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Organisation\Models\StaffWorkCategory;

class StaffWorkCategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'OFF', 'name' => 'Office Staff'],
            ['code' => 'SHO', 'name' => 'Shop and Office'],
        ];

        foreach ($rows as $row) {
            StaffWorkCategory::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'is_active' => true,
                ],
            );
        }
    }
}
