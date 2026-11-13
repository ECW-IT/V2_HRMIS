<?php

namespace Modules\Organisation\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
