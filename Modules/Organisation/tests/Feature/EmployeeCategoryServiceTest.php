<?php

namespace Modules\Organisation\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Organisation\Exceptions\DuplicateLevel;
use Modules\Organisation\Exceptions\DuplicateName;
use Modules\Organisation\Exceptions\InvalidCategoryBreak;
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
        app(EmployeeCategoryService::class)->create(['code' => 'FAC', 'name' => 'Factory', 'level' => 1]);
        $this->expectException(DuplicateName::class);
        app(EmployeeCategoryService::class)->create(['code' => 'FAC2', 'name' => ' factory ', 'level' => 2]);
    }

    public function test_category_level_must_be_unique(): void
    {
        app(EmployeeCategoryService::class)->create(['code' => 'FAC', 'name' => 'Factory', 'level' => 1]);
        $this->expectException(DuplicateLevel::class);
        app(EmployeeCategoryService::class)->create(['code' => 'OFF', 'name' => 'Office', 'level' => 1]);
    }

    public function test_renaming_a_category_to_an_existing_name_is_refused(): void
    {
        $svc = app(EmployeeCategoryService::class);
        $svc->create(['code' => 'FAC', 'name' => 'Factory', 'level' => 1]);
        $b = $svc->create(['code' => 'OFF', 'name' => 'Office', 'level' => 2]);
        $this->expectException(DuplicateName::class);
        $svc->update($b, ['name' => 'FACTORY']);
    }

    public function test_category_table_holds_only_the_label_columns(): void
    {
        $cols = Schema::getColumnListing('employee_categories');
        sort($cols);

        $this->assertSame(
            ['code', 'created_at', 'early_ot', 'evening_ot', 'id', 'is_active', 'late_deduction', 'level', 'name', 'name_key', 'updated_at'],
            $cols,
        );
    }

    public function test_old_duty_tables_stay_gone(): void
    {
        $this->assertFalse(Schema::hasTable('employee_category_duty_schedules'));
        $this->assertFalse(Schema::hasTable('break_types'));
        $this->assertTrue(Schema::hasTable('employee_category_breaks'));
    }

    public function test_create_ignores_rule_keys(): void
    {
        $category = app(EmployeeCategoryService::class)->create([
            'code' => 'FAC',
            'name' => 'Factory',
            'level' => 1,
            'on_duty_time' => '08:00',
            'normal_ot_rate' => 240,
        ]);

        $this->assertSame('FAC', $category->code);
        $this->assertSame('Factory', $category->name);
        $this->assertSame(1, $category->level);
        $this->assertTrue($category->is_active);
    }

    public function test_update_changes_code_name_level_and_active(): void
    {
        $svc = app(EmployeeCategoryService::class);
        $category = $svc->create(['code' => 'FAC', 'name' => 'Factory', 'level' => 1]);

        $svc->update($category, [
            'code' => 'WRK',
            'name' => 'Workshop',
            'level' => 3,
            'is_active' => false,
        ]);

        $fresh = $category->fresh();
        $this->assertSame('WRK', $fresh->code);
        $this->assertSame('Workshop', $fresh->name);
        $this->assertSame('workshop', $fresh->name_key);
        $this->assertSame(3, $fresh->level);
        $this->assertFalse($fresh->is_active);
    }

    public function test_category_time_rules_and_breaks_are_saved(): void
    {
        $category = app(EmployeeCategoryService::class)->create([
            'code' => 'FAC',
            'name' => 'Factory',
            'level' => 1,
            'early_ot' => true,
            'evening_ot' => false,
            'late_deduction' => true,
            'breaks' => [
                ['name' => 'Lunch', 'start_time' => '12:00', 'end_time' => '12:30', 'is_paid' => false],
                ['name' => 'Tea', 'start_time' => '15:00', 'end_time' => '15:15', 'is_paid' => true],
            ],
        ]);

        $this->assertTrue($category->early_ot);
        $this->assertFalse($category->evening_ot);
        $this->assertTrue($category->late_deduction);
        $this->assertSame(['Lunch', 'Tea'], $category->breaks->pluck('name')->all());
    }

    public function test_a_break_that_ends_before_it_starts_is_refused(): void
    {
        $this->expectException(InvalidCategoryBreak::class);

        app(EmployeeCategoryService::class)->create([
            'code' => 'FAC',
            'name' => 'Factory',
            'level' => 1,
            'breaks' => [
                ['name' => 'Lunch', 'start_time' => '13:00', 'end_time' => '12:00'],
            ],
        ]);
    }
}
