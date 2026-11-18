<?php

namespace Modules\CoreHr\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\CoreHr\Models\Region;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        // V1 "Region" options on employeeView (religion values).
        $rows = [
            ['code' => 'BUDDHISM', 'name' => 'Buddhism', 'sort_order' => 1],
            ['code' => 'CHRISTIAN', 'name' => 'Christian', 'sort_order' => 2],
            ['code' => 'MUSLIM', 'name' => 'Muslim', 'sort_order' => 3],
            ['code' => 'HINDU', 'name' => 'Hindu', 'sort_order' => 4],
        ];

        foreach ($rows as $row) {
            Region::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }
}
