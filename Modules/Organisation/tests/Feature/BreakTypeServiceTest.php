<?php

namespace Modules\Organisation\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organisation\Exceptions\DuplicateName;
use Modules\Organisation\Services\BreakTypeService;
use Tests\TestCase;

class BreakTypeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_break_type_name_must_be_unique_ignoring_case(): void
    {
        app(BreakTypeService::class)->create(['name' => 'Tea']);
        $this->expectException(DuplicateName::class);
        app(BreakTypeService::class)->create(['name' => ' tea ']);
    }
}
