<?php

namespace Modules\Payroll\Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Payroll\Database\Seeders\PayComponentSeeder;
use Modules\Payroll\Enums\CalcMethod;
use Modules\Payroll\Enums\FundCode;
use Modules\Payroll\Exceptions\InvalidPayComponent;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Services\PayComponentService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayComponentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayComponentService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Permission::findOrCreate('payroll.config');
        Permission::findOrCreate('payroll.view');
        Role::findOrCreate('hr-manager');
        Role::findOrCreate('payroll-officer');

        $this->svc = app(PayComponentService::class);

        $user = User::factory()->allCompanies()->create(['must_change_password' => false]);
        $user->givePermissionTo('payroll.config');
        $this->actingAs($user);
    }

    public function test_seeder_creates_the_system_components_once(): void
    {
        $this->seed(PayComponentSeeder::class);
        $this->seed(PayComponentSeeder::class);

        $this->assertSame(12, PayComponent::query()->where('is_system', true)->count());

        $basic = PayComponent::query()->where('code', 'BASIC')->first();
        $this->assertNotNull($basic);
        $this->assertSame(CalcMethod::EARNED_BASIC, $basic->method);
        $this->assertSame(1, $basic->sheet);
        $this->assertTrue($basic->epf_liable);
        $this->assertTrue($basic->taxable);
    }

    public function test_sheet_2_component_cannot_be_epf_liable(): void
    {
        $this->expectException(InvalidPayComponent::class);
        $this->expectExceptionMessage('A sheet 2 component cannot be EPF-liable.');

        $this->svc->create([
            'code' => 'X2',
            'name' => 'Sheet two',
            'kind' => 'EARNING',
            'method' => 'FIXED',
            'sheet' => 2,
            'epf_liable' => true,
            'taxable' => true,
            'display_order' => 1,
        ]);
    }

    public function test_code_format_and_uniqueness(): void
    {
        foreach (['perf', '1ABC', 'TOO_LONG_CODE_OVER_TWENTY'] as $code) {
            try {
                $this->svc->create([
                    'code' => $code,
                    'name' => 'Bad',
                    'kind' => 'EARNING',
                    'method' => 'FIXED',
                    'sheet' => 1,
                    'display_order' => 1,
                ]);
                $this->fail("Expected InvalidPayComponent for code {$code}");
            } catch (InvalidPayComponent) {
                // expected
            }
        }

        $this->svc->create([
            'code' => 'PERF_A',
            'name' => 'First',
            'kind' => 'EARNING',
            'method' => 'FIXED',
            'sheet' => 1,
            'display_order' => 1,
        ]);

        $this->expectException(InvalidPayComponent::class);
        $this->svc->create([
            'code' => 'PERF_A',
            'name' => 'Second',
            'kind' => 'EARNING',
            'method' => 'FIXED',
            'sheet' => 1,
            'display_order' => 2,
        ]);
    }

    public function test_code_cannot_be_changed(): void
    {
        $c = $this->svc->create([
            'code' => 'ALLOW_A',
            'name' => 'Allowance',
            'kind' => 'EARNING',
            'method' => 'FIXED',
            'sheet' => 1,
            'display_order' => 1,
        ]);

        $this->expectException(InvalidPayComponent::class);
        $this->svc->update($c, ['code' => 'ALLOW_B']);
    }

    public function test_user_cannot_create_a_calculator_method(): void
    {
        foreach (['EARNED_BASIC', 'EPF_EMPLOYEE'] as $method) {
            try {
                $this->svc->create([
                    'code' => 'USR_'.$method,
                    'name' => 'Bad method',
                    'kind' => $method === 'EPF_EMPLOYEE' ? 'DEDUCTION' : 'EARNING',
                    'method' => $method,
                    'sheet' => 1,
                    'display_order' => 1,
                ]);
                $this->fail("Expected InvalidPayComponent for method {$method}");
            } catch (InvalidPayComponent) {
                // expected
            }
        }
    }

    public function test_system_component_cannot_change_kind_method_or_be_deactivated(): void
    {
        $this->seed(PayComponentSeeder::class);
        $basic = PayComponent::query()->where('code', 'BASIC')->firstOrFail();

        try {
            $this->svc->update($basic, ['kind' => 'DEDUCTION']);
            $this->fail('Expected InvalidPayComponent for kind change');
        } catch (InvalidPayComponent) {
            // expected
        }

        try {
            $this->svc->update($basic, ['method' => 'FIXED']);
            $this->fail('Expected InvalidPayComponent for method change');
        } catch (InvalidPayComponent) {
            // expected
        }

        $this->expectException(InvalidPayComponent::class);
        $this->svc->deactivate($basic);
    }

    public function test_system_component_name_and_order_can_change(): void
    {
        $this->seed(PayComponentSeeder::class);
        $basic = PayComponent::query()->where('code', 'BASIC')->firstOrFail();

        $updated = $this->svc->update($basic, [
            'name' => 'Basic pay',
            'display_order' => 15,
        ]);

        $this->assertSame('Basic pay', $updated->name);
        $this->assertSame(15, $updated->display_order);
        $this->assertSame(CalcMethod::EARNED_BASIC, $updated->method);
    }

    public function test_epf_and_tax_flags_only_on_earnings(): void
    {
        $this->expectException(InvalidPayComponent::class);
        $this->svc->create([
            'code' => 'DED_TAX',
            'name' => 'Bad deduction',
            'kind' => 'DEDUCTION',
            'method' => 'FIXED',
            'sheet' => 1,
            'taxable' => true,
            'display_order' => 1,
        ]);
    }

    public function test_fund_only_on_a_fixed_deduction(): void
    {
        try {
            $this->svc->create([
                'code' => 'EARN_FUND',
                'name' => 'Bad earning fund',
                'kind' => 'EARNING',
                'method' => 'FIXED',
                'sheet' => 1,
                'fund' => 'SAVINGS',
                'taxable' => true,
                'display_order' => 1,
            ]);
            $this->fail('Expected InvalidPayComponent for fund on earning');
        } catch (InvalidPayComponent) {
            // expected
        }

        $ok = $this->svc->create([
            'code' => 'FUND_OK',
            'name' => 'Savings',
            'kind' => 'DEDUCTION',
            'method' => 'FIXED',
            'sheet' => 1,
            'fund' => 'SAVINGS',
            'display_order' => 1,
        ]);

        $this->assertSame(FundCode::SAVINGS, $ok->fund);
    }

    public function test_night_units_needs_base_hours(): void
    {
        try {
            $this->svc->create([
                'code' => 'NIGHT_X',
                'name' => 'Night',
                'kind' => 'EARNING',
                'method' => 'NIGHT_UNITS',
                'sheet' => 2,
                'taxable' => true,
                'display_order' => 1,
            ]);
            $this->fail('Expected InvalidPayComponent without night_base_hours');
        } catch (InvalidPayComponent) {
            // expected
        }

        $this->expectException(InvalidPayComponent::class);
        $this->svc->create([
            'code' => 'FIXED_N',
            'name' => 'Fixed with night',
            'kind' => 'EARNING',
            'method' => 'FIXED',
            'sheet' => 1,
            'night_base_hours' => 5,
            'taxable' => true,
            'display_order' => 1,
        ]);
    }

    public function test_catalogue_is_active_components_keyed_by_code_in_display_order(): void
    {
        $b = $this->svc->create([
            'code' => 'CAT_B',
            'name' => 'B',
            'kind' => 'EARNING',
            'method' => 'FIXED',
            'sheet' => 1,
            'display_order' => 20,
        ]);
        $a = $this->svc->create([
            'code' => 'CAT_A',
            'name' => 'A',
            'kind' => 'EARNING',
            'method' => 'FIXED',
            'sheet' => 1,
            'display_order' => 10,
        ]);
        $this->svc->create([
            'code' => 'CAT_C',
            'name' => 'C',
            'kind' => 'EARNING',
            'method' => 'FIXED',
            'sheet' => 1,
            'display_order' => 30,
        ]);
        $this->svc->deactivate($b);

        $catalogue = $this->svc->catalogue();

        $this->assertSame(['CAT_A', 'CAT_C'], $catalogue->keys()->all());
        $this->assertSame($a->id, $catalogue->get('CAT_A')->id);
    }

    public function test_by_code_throws_for_an_unknown_code(): void
    {
        $this->expectException(InvalidPayComponent::class);
        $this->svc->byCode('NO_SUCH');
    }

    public function test_writes_need_payroll_config(): void
    {
        $manager = User::factory()->allCompanies()->create(['must_change_password' => false]);
        $manager->assignRole('hr-manager');
        $this->actingAs($manager);

        $this->expectException(AuthorizationException::class);
        $this->svc->create([
            'code' => 'NO_AUTH',
            'name' => 'No auth',
            'kind' => 'EARNING',
            'method' => 'FIXED',
            'sheet' => 1,
            'display_order' => 1,
        ]);
    }

    public function test_changes_are_audited(): void
    {
        $c = $this->svc->create([
            'code' => 'AUD_1',
            'name' => 'Audited',
            'kind' => 'EARNING',
            'method' => 'FIXED',
            'sheet' => 1,
            'display_order' => 1,
        ]);

        $log = AuditLog::query()
            ->where('auditable_type', PayComponent::class)
            ->where('auditable_id', $c->id)
            ->where('event', 'CREATED')
            ->sole();

        $this->assertSame(auth()->id(), $log->user_id);
    }
}
