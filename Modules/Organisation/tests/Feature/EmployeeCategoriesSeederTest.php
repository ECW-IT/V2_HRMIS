<?php

namespace Modules\Organisation\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organisation\Database\Seeders\EmployeeCategoriesSeeder;
use Modules\Organisation\Models\EmployeeCategory;
use Tests\TestCase;

class EmployeeCategoriesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_loads_categories_with_levels_and_is_idempotent(): void
    {
        $expected = [
            'DIRECTOR' => ['name' => 'Director', 'level' => 1],
            'HOD' => ['name' => 'HOD', 'level' => 2],
            'CONSULTANT' => ['name' => 'Consultant', 'level' => 3],
            'MANAGER' => ['name' => 'Manager', 'level' => 4],
            'EXECUTIVE' => ['name' => 'Executive', 'level' => 5],
            'PRODUCTION' => ['name' => 'Production', 'level' => 6],
            'OFFICE_STAFF' => ['name' => 'Office Staff', 'level' => 7],
            'WORKER' => ['name' => 'Worker', 'level' => 8],
        ];

        $this->seed(EmployeeCategoriesSeeder::class);

        $this->assertSame(count($expected), EmployeeCategory::query()->count());

        foreach ($expected as $code => $data) {
            $category = EmployeeCategory::query()->where('code', $code)->sole();
            $this->assertSame($data['name'], $category->name);
            $this->assertSame($data['level'], $category->level);
            $this->assertTrue($category->is_active);
        }

        $this->seed(EmployeeCategoriesSeeder::class);
        $this->assertSame(count($expected), EmployeeCategory::query()->count());
    }
}
