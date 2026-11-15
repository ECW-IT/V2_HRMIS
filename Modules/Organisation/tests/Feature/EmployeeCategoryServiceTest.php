<?php

namespace Modules\Organisation\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Organisation\Exceptions\DuplicateName;
use Modules\Organisation\Services\EmployeeCategoryService;
use Tests\TestCase;

class EmployeeCategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_name_key_normalises_case_and_spaces(): void
    {
        $this->assertSame('factory shift', EmployeeCategoryService::nameKey('  Factory   Shift '));
    }

    public function test_category_name_must_be_unique_ignoring_case_and_spaces(): void
    {
        app(EmployeeCategoryService::class)->create(['code' => 'FAC', 'name' => 'Factory']);
        $this->expectException(DuplicateName::class);
        app(EmployeeCategoryService::class)->create(['code' => 'FAC2', 'name' => ' factory ']);
    }

    public function test_renaming_a_category_to_an_existing_name_is_refused(): void
    {
        $svc = app(EmployeeCategoryService::class);
        $svc->create(['code' => 'FAC', 'name' => 'Factory']);
        $b = $svc->create(['code' => 'OFF', 'name' => 'Office']);
        $this->expectException(DuplicateName::class);
        $svc->update($b, ['name' => 'FACTORY']);
    }

    public function test_category_table_holds_only_the_label_columns(): void
    {
        $cols = Schema::getColumnListing('employee_categories');
        sort($cols);

        $this->assertSame(
            ['code', 'created_at', 'id', 'is_active', 'name', 'name_key', 'updated_at'],
            $cols,
        );
    }

    public function test_rule_tables_are_gone(): void
    {
        $this->assertFalse(Schema::hasTable('employee_category_duty_schedules'));
        $this->assertFalse(Schema::hasTable('employee_category_breaks'));
        $this->assertFalse(Schema::hasTable('break_types'));
    }

    public function test_create_ignores_rule_keys(): void
    {
        $category = app(EmployeeCategoryService::class)->create([
            'code' => 'FAC',
            'name' => 'Factory',
            'on_duty_time' => '08:00',
            'normal_ot_rate' => 240,
        ]);

        $this->assertSame('FAC', $category->code);
        $this->assertSame('Factory', $category->name);
        $this->assertTrue($category->is_active);
    }

    public function test_update_changes_code_name_and_active(): void
    {
        $svc = app(EmployeeCategoryService::class);
        $category = $svc->create(['code' => 'FAC', 'name' => 'Factory']);

        $svc->update($category, [
            'code' => 'WRK',
            'name' => 'Workshop',
            'is_active' => false,
        ]);

        $fresh = $category->fresh();
        $this->assertSame('WRK', $fresh->code);
        $this->assertSame('Workshop', $fresh->name);
        $this->assertSame('workshop', $fresh->name_key);
        $this->assertFalse($fresh->is_active);
    }
}
