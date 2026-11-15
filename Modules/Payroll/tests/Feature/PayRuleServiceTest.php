<?php

namespace Modules\Payroll\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organisation\Models\Grade;
use Modules\Payroll\Database\Seeders\PayRuleSeeder;
use Modules\Payroll\Enums\ExtraDayMethod;
use Modules\Payroll\Exceptions\InvalidPayRule;
use Modules\Payroll\Models\StatutoryRate;
use Modules\Payroll\Models\TaxSlab;
use Modules\Payroll\Services\PayRuleService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class PayRuleServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private PayRuleService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Permission::findOrCreate('payroll.config');
        Permission::findOrCreate('payroll.view');
        Role::findOrCreate('hr-manager');

        $this->svc = app(PayRuleService::class);

        $user = User::factory()->allCompanies()->create(['must_change_password' => false]);
        $user->givePermissionTo('payroll.config');
        $this->actingAs($user);
    }

    public function test_rules_for_returns_the_values_in_force_on_the_date(): void
    {
        $c = $this->company();
        $this->seed(PayRuleSeeder::class);

        $this->svc->setStatutoryRate('EPF_EE', '10.00', '2027-01-01');

        $this->assertSame('8.00', $this->svc->rulesFor($c, '2026-12-31')->epfEmployeePercent);
        $this->assertSame('10.00', $this->svc->rulesFor($c, '2027-01-01')->epfEmployeePercent);
    }

    public function test_rules_for_names_everything_that_is_missing(): void
    {
        $c = $this->company(['name' => 'Acme Co']);

        try {
            $this->svc->rulesFor($c, '2026-06-01');
            $this->fail('Expected InvalidPayRule');
        } catch (InvalidPayRule $e) {
            $message = $e->getMessage();
            $this->assertStringContainsString('EPF_EE', $message);
            $this->assertStringContainsString('EPF_ER', $message);
            $this->assertStringContainsString('ETF', $message);
            $this->assertStringContainsString('tax table', $message);
            $this->assertStringContainsString('Acme Co', $message);
        }
    }

    public function test_tax_table_is_replaced_as_a_whole(): void
    {
        $c = $this->company();
        $this->seed(PayRuleSeeder::class);

        $this->svc->setTaxTable('2027-04-01', [
            ['width' => '200000.00', 'percent' => '0'],
            ['width' => null, 'percent' => '20'],
        ]);

        $this->assertCount(6, $this->svc->rulesFor($c, '2027-03-31')->taxSlabs);
        $this->assertCount(2, $this->svc->rulesFor($c, '2027-04-01')->taxSlabs);

        $old = TaxSlab::query()->whereDate('effective_from', '2026-01-01')->get();
        $this->assertTrue($old->isNotEmpty());
        foreach ($old as $row) {
            $this->assertSame('2027-03-31', $row->effective_to->toDateString());
        }
    }

    public function test_tax_table_needs_exactly_one_open_ended_last_slab(): void
    {
        try {
            $this->svc->setTaxTable('2027-01-01', [
                ['width' => '100000.00', 'percent' => '0'],
                ['width' => '50000.00', 'percent' => '6'],
            ]);
            $this->fail('Expected InvalidPayRule for no null width');
        } catch (InvalidPayRule) {
            // expected
        }

        try {
            $this->svc->setTaxTable('2027-01-01', [
                ['width' => null, 'percent' => '0'],
                ['width' => '50000.00', 'percent' => '6'],
            ]);
            $this->fail('Expected InvalidPayRule for null width in the middle');
        } catch (InvalidPayRule) {
            // expected
        }

        $this->expectException(InvalidPayRule::class);
        $this->svc->setTaxTable('2027-01-01', [
            ['width' => '100000.00', 'percent' => '0'],
            ['width' => null, 'percent' => '6'],
            ['width' => null, 'percent' => '20'],
        ]);
    }

    public function test_effective_from_must_be_the_first_of_a_month(): void
    {
        $this->expectException(InvalidPayRule::class);
        $this->svc->setStatutoryRate('EPF_EE', '8.00', '2027-01-15');
    }

    public function test_tier_lookup_by_code_and_date(): void
    {
        $this->seed(PayRuleSeeder::class);

        $t1 = $this->svc->tier('T1', '2026-06-01');
        $this->assertNotNull($t1);
        $this->assertSame('5000.00', $t1->base_amount);
        $this->assertSame('1000.00', $t1->per_day_rate);

        $this->svc->setTier('T1', 'Tier 1 revised', '6000.00', '1100.00', '2027-01-01');

        $this->assertSame('5000.00', $this->svc->tier('T1', '2026-12-31')->base_amount);
        $this->assertSame('6000.00', $this->svc->tier('T1', '2027-01-01')->base_amount);
        $this->assertNull($this->svc->tier('T9', '2026-06-01'));
    }

    public function test_extra_day_method_per_grade_and_grades_without_rule(): void
    {
        $a = Grade::factory()->create(['code' => 'G1', 'name' => 'Grade 1', 'rank' => 1, 'is_active' => true]);
        $b = Grade::factory()->create(['code' => 'G2', 'name' => 'Grade 2', 'rank' => 2, 'is_active' => true]);

        $this->svc->setExtraDayMethod($a, ExtraDayMethod::HOURLY, '2026-01-01');

        $this->assertSame(ExtraDayMethod::HOURLY, $this->svc->extraDayMethod($a->id, '2026-06-01'));
        $this->assertNull($this->svc->extraDayMethod($b->id, '2026-06-01'));

        $missing = $this->svc->gradesWithoutRule('2026-06-01');
        $this->assertTrue($missing->contains(fn (Grade $g) => $g->id === $b->id));
        $this->assertFalse($missing->contains(fn (Grade $g) => $g->id === $a->id));
    }

    public function test_company_settings_are_scoped_and_validated(): void
    {
        $c = $this->company();

        try {
            $this->svc->setCompanySettings($c, [
                'late_divisor' => 0,
                'attendance_min_offset' => '2.0',
                'attendance_max_offset' => '3.0',
                'hours_per_month' => 240,
                'hours_per_day' => 8,
                'ot_multiplier' => '1.50',
            ], '2026-01-01');
            $this->fail('Expected InvalidPayRule for late_divisor 0');
        } catch (InvalidPayRule) {
            // expected
        }

        $this->expectException(InvalidPayRule::class);
        $this->svc->setCompanySettings($c, [
            'late_divisor' => 14400,
            'attendance_min_offset' => '2.0',
            'attendance_max_offset' => '3.0',
            'hours_per_month' => 240,
            'hours_per_day' => 8,
            'ot_multiplier' => '0.5',
        ], '2026-01-01');
    }

    public function test_seeder_does_not_overwrite_changed_values(): void
    {
        $this->seed(PayRuleSeeder::class);

        $rate = StatutoryRate::query()->where('code', 'EPF_EE')->whereDate('effective_from', '2026-01-01')->firstOrFail();
        $rate->update(['percent' => '9.00']);

        $this->seed(PayRuleSeeder::class);

        $this->assertSame('9.00', $rate->fresh()->percent);
    }

    public function test_writes_need_payroll_config(): void
    {
        $manager = User::factory()->allCompanies()->create(['must_change_password' => false]);
        $manager->assignRole('hr-manager');
        $this->actingAs($manager);

        $this->expectException(AuthorizationException::class);
        $this->svc->setStatutoryRate('EPF_EE', '8.00', '2027-01-01');
    }
}
