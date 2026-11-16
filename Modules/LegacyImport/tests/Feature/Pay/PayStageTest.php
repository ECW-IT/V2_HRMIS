<?php

namespace Modules\LegacyImport\Tests\Feature\Pay;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Modules\LegacyImport\Models\StgPayProfile;
use Modules\LegacyImport\Tests\Support\LegacyFixtures;
use Modules\Organisation\Models\Company;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class PayStageTest extends TestCase
{
    use HrFixtures;
    use LegacyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLegacyFixtures();
    }

    public function test_stages_only_active_profiles_of_the_company(): void
    {
        $co1 = Company::query()->where('code', 'CO1')->firstOrFail();
        $this->legacyProfile(['emp_no' => 'ECW 101', 'branch_id' => 1, 'status' => 1]);
        $this->legacyProfile(['emp_no' => 'ECW 102', 'branch_id' => 1, 'status' => 0]);
        $this->legacyProfile(['emp_no' => 'ECW 201', 'branch_id' => 2, 'status' => 1]);

        $this->artisan('hrmis:import:pay-stage', ['--company' => 'CO1'])->assertSuccessful();

        $empNos = StgPayProfile::query()->where('company_id', $co1->id)->pluck('emp_no')->all();
        $this->assertSame(['ECW 101'], $empNos);
    }

    public function test_staging_twice_replaces_its_own_rows(): void
    {
        $this->legacyProfile(['emp_no' => 'ECW 101', 'branch_id' => 1, 'status' => 1]);
        $this->artisan('hrmis:import:pay-stage', ['--company' => 'CO1'])->assertSuccessful();
        $this->assertSame(1, StgPayProfile::query()->count());

        $this->legacyProfile(['emp_no' => 'ECW 102', 'branch_id' => 1, 'status' => 1]);
        $this->artisan('hrmis:import:pay-stage', ['--company' => 'CO1'])->assertSuccessful();

        $this->assertSame(2, StgPayProfile::query()->where('company_code', 'CO1')->count());
        $this->assertSame(
            ['ECW 101', 'ECW 102'],
            StgPayProfile::query()->orderBy('emp_no')->pluck('emp_no')->all()
        );
    }

    public function test_fails_clearly_without_the_legacy_connection(): void
    {
        Config::set('database.connections.legacy_payroll.database', '');

        $this->artisan('hrmis:import:pay-stage')
            ->expectsOutputToContain('legacy_payroll connection is not configured')
            ->assertFailed();
    }
}
