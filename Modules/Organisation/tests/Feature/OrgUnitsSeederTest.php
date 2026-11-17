<?php

namespace Modules\Organisation\Tests\Feature;

use Database\Seeders\OrganisationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organisation\Database\Seeders\OrgUnitsSeeder;
use Modules\Organisation\Enums\OrgUnitType;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\OrgUnit;
use Tests\TestCase;

class OrgUnitsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_loads_key_departments_and_is_idempotent(): void
    {
        $this->seed(OrganisationSeeder::class);

        $co1 = Company::query()->where('code', 'CO1')->sole();

        $this->seed(OrgUnitsSeeder::class);
        $firstCount = OrgUnit::query()->where('company_id', $co1->id)->count();
        $this->assertGreaterThan(0, $firstCount);

        foreach (['PROD', 'SALES', 'STORES', 'WELD'] as $code) {
            $this->assertTrue(
                OrgUnit::query()->where('company_id', $co1->id)->where('code', $code)->exists(),
                "Expected department {$code} for CO1",
            );
        }

        $digitalMarketing = OrgUnit::query()
            ->where('company_id', $co1->id)
            ->where('code', 'SALES_DM')
            ->sole();
        $this->assertSame(OrgUnitType::SECTION, $digitalMarketing->type);
        $this->assertSame(
            'SALES',
            OrgUnit::query()->findOrFail($digitalMarketing->parent_id)->code,
        );

        $this->seed(OrgUnitsSeeder::class);
        $this->assertSame($firstCount, OrgUnit::query()->where('company_id', $co1->id)->count());
    }
}
