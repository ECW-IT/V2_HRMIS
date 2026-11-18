<?php

namespace Modules\CoreHr\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\CoreHr\Models\Relationship;

class RelationshipSeeder extends Seeder
{
    public function run(): void
    {
        // V1 emergency/dependent options (employeeView.blade.php).
        $rows = [
            ['code' => 'SON', 'name' => 'Son', 'sort_order' => 1],
            ['code' => 'DAUGHTER', 'name' => 'Daughter', 'sort_order' => 2],
            ['code' => 'WIFE', 'name' => 'Wife', 'sort_order' => 3],
            ['code' => 'HUSBAND', 'name' => 'Husband', 'sort_order' => 4],
            ['code' => 'FATHER', 'name' => 'Father', 'sort_order' => 5],
            ['code' => 'MOTHER', 'name' => 'Mother', 'sort_order' => 6],
            ['code' => 'BROTHER', 'name' => 'Brother', 'sort_order' => 7],
            ['code' => 'SISTER', 'name' => 'Sister', 'sort_order' => 8],
        ];

        foreach ($rows as $row) {
            Relationship::query()->updateOrCreate(
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
