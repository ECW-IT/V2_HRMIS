<?php

namespace Modules\Payroll\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Services\PayPeriodService;
use Modules\Organisation\Models\Company;
use Modules\Payroll\Enums\PayEntryStatus;
use Modules\Payroll\Exceptions\InvalidPayEntry;
use Modules\Payroll\Services\CompensationService;
use Modules\Payroll\Services\PayEntryService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class PayEntryServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private PayEntryService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->svc = app(PayEntryService::class);
        $this->travelTo('2026-12-15');
    }

    private function manager($e): User
    {
        return User::factory()
            ->forCompanies([$e->company])
            ->withRole('payroll-officer')
            ->create(['must_change_password' => false]);
    }

    private function decemberPeriod($company)
    {
        return app(PayPeriodService::class)->ensure($company, 2026, 12);
    }

    public function test_amount_entry_for_an_entry_component(): void
    {
        $e = $this->paid();
        $by = $this->manager($e);
        $p = $this->decemberPeriod($e->company);

        $entry = $this->svc->add($e, $p, [
            'component_code' => 'MEALS',
            'amount' => '3000.00',
        ], $by);

        $this->assertSame(PayEntryStatus::ACTIVE, $entry->status);
        $this->assertSame('3000.00', $entry->amount);
        $this->assertNull($entry->quantity);
        $this->assertSame($by->id, $entry->created_by);
    }

    public function test_entry_component_refuses_a_quantity_and_needs_a_positive_amount(): void
    {
        $e = $this->paid();
        $by = $this->manager($e);
        $p = $this->decemberPeriod($e->company);

        try {
            $this->svc->add($e, $p, [
                'component_code' => 'MEALS',
                'quantity' => '2.00',
                'amount' => '3000.00',
            ], $by);
            $this->fail();
        } catch (InvalidPayEntry) {
            // expected
        }

        $this->expectException(InvalidPayEntry::class);
        $this->svc->add($e, $p, [
            'component_code' => 'MEALS',
            'amount' => '0.00',
        ], $by);
    }

    public function test_quantity_entry_needs_the_employee_to_have_a_rate(): void
    {
        $e = $this->paid();
        $by = $this->manager($e);
        $p = $this->decemberPeriod($e->company);

        try {
            $this->svc->add($e, $p, [
                'component_code' => 'VIP_JOBS',
                'quantity' => '6.00',
            ], $by);
            $this->fail();
        } catch (InvalidPayEntry $ex) {
            $this->assertStringContainsString('has no rate for', $ex->getMessage());
        }

        app(CompensationService::class)->addComponent($e, 'VIP_JOBS', '500.00', '2026-01-01');

        $entry = $this->svc->add($e, $p, [
            'component_code' => 'VIP_JOBS',
            'quantity' => '6.00',
        ], $by);

        $this->assertSame('6.00', $entry->quantity);
        $this->assertNull($entry->amount);
    }

    public function test_night_hours_are_a_quantity_entry(): void
    {
        $e = $this->paid();
        $by = $this->manager($e);
        $p = $this->decemberPeriod($e->company);
        app(CompensationService::class)->addComponent($e, 'NIGHT_5', '100.00', '2026-01-01');

        $entry = $this->svc->add($e, $p, [
            'component_code' => 'NIGHT_5',
            'quantity' => '20.00',
        ], $by);
        $this->assertSame('20.00', $entry->quantity);

        $this->expectException(InvalidPayEntry::class);
        $this->svc->add($e, $p, [
            'component_code' => 'NIGHT_5',
            'quantity' => '20.00',
            'amount' => '100.00',
        ], $by);
    }

    public function test_fixed_and_calculated_components_cannot_be_entered(): void
    {
        $e = $this->paid();
        $by = $this->manager($e);
        $p = $this->decemberPeriod($e->company);

        foreach (['FUEL', 'BASIC', 'EPF_EE'] as $code) {
            try {
                $this->svc->add($e, $p, [
                    'component_code' => $code,
                    'amount' => '100.00',
                ], $by);
                $this->fail("Expected InvalidPayEntry for {$code}");
            } catch (InvalidPayEntry $ex) {
                $this->assertStringContainsString('is not entered per period', $ex->getMessage());
            }
        }
    }

    public function test_several_entries_of_one_component_are_allowed(): void
    {
        $e = $this->paid();
        $by = $this->manager($e);
        $p = $this->decemberPeriod($e->company);

        $this->svc->add($e, $p, ['component_code' => 'MEALS', 'amount' => '1000.00'], $by);
        $this->svc->add($e, $p, ['component_code' => 'MEALS', 'amount' => '2000.00'], $by);

        $this->assertCount(2, $this->svc->for($e->id, $p));
    }

    public function test_employee_must_be_employed_in_the_period(): void
    {
        $this->travelTo('2027-01-05');
        $e = $this->paid('2027-01-05');
        $by = $this->manager($e);
        $p = $this->decemberPeriod($e->company);
        $this->expectException(InvalidPayEntry::class);
        $this->svc->add($e, $p, ['component_code' => 'MEALS', 'amount' => '1000.00'], $by);
    }

    public function test_cancel_marks_the_entry_and_for_ignores_it(): void
    {
        $e = $this->paid();
        $by = $this->manager($e);
        $p = $this->decemberPeriod($e->company);

        $entry = $this->svc->add($e, $p, ['component_code' => 'MEALS', 'amount' => '1000.00'], $by);
        $cancelled = $this->svc->cancel($entry, $by);

        $this->assertSame(PayEntryStatus::CANCELLED, $cancelled->status);
        $this->assertSame($by->id, $cancelled->cancelled_by);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertCount(0, $this->svc->for($e->id, $p));
    }

    public function test_entries_are_limited_to_the_users_companies(): void
    {
        $e = $this->paid();
        $by = $this->manager($e);
        $p = $this->decemberPeriod($e->company);
        $this->svc->add($e, $p, ['component_code' => 'MEALS', 'amount' => '1000.00'], $by);

        $other = Company::factory()->create();
        $outsider = User::factory()
            ->forCompanies([$other])
            ->withRole('payroll-officer')
            ->create(['must_change_password' => false]);

        $this->actingAs($outsider);
        $this->assertCount(0, $this->svc->for($e->id, $p));

        $this->expectException(AuthorizationException::class);
        $this->svc->add($e, $p, ['component_code' => 'MEALS', 'amount' => '500.00'], $outsider);
    }

    public function test_writes_need_pay_entry_manage(): void
    {
        $e = $this->paid();
        $p = $this->decemberPeriod($e->company);
        $officer = User::factory()
            ->forCompanies([$e->company])
            ->withRole('hr-officer')
            ->create(['must_change_password' => false]);

        $this->expectException(AuthorizationException::class);
        $this->svc->add($e, $p, ['component_code' => 'MEALS', 'amount' => '1000.00'], $officer);
    }
}
