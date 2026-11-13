<?php

namespace Tests\Support;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Modules\CoreHr\Models\Bank;
use Modules\CoreHr\Models\BankBranch;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\Person;
use Modules\CoreHr\Services\EmploymentService;
use Modules\CoreHr\Services\PersonService;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Grade;
use Modules\Organisation\Models\Job;
use Modules\Organisation\Models\Location;
use Modules\Organisation\Models\OrgUnit;
use Spatie\Permission\Models\Role;

trait HrFixtures
{
    private static int $personSerial = 1;

    private static int $epfSerial = 1;

    protected function company(array $attrs = []): Company
    {
        return Company::factory()->create($attrs);
    }

    /** Acts as a user holding $role for the given companies, with must_change_password = false. */
    protected function loginAs(string $role, Company ...$companies): User
    {
        if (! Role::query()->where('name', $role)->exists()) {
            $this->seed(RolesAndPermissionsSeeder::class);
        }

        $user = User::factory()
            ->forCompanies($companies)
            ->withRole($role)
            ->create(['must_change_password' => false]);

        $this->actingAs($user);

        return $user;
    }

    protected function person(array $attrs = []): Person
    {
        $n = self::$personSerial++;
        $nic = sprintf('1988100%05d', $n);

        return app(PersonService::class)->create(array_merge([
            'nic' => $nic,
            'first_name' => 'Test',
            'last_name' => 'Person'.$n,
            'name_with_initials' => 'T. Person'.$n,
            'date_of_birth' => '1988-04-09',
            'gender' => 'MALE',
        ], $attrs));
    }

    /** @return array{org_unit_id: int, job_id: int, grade_id: int, location_id: int} */
    protected function asg(Company $c, array $overrides = []): array
    {
        return array_merge([
            'org_unit_id' => OrgUnit::factory()->create(['company_id' => $c->id])->id,
            'job_id' => Job::factory()->create()->id,
            'grade_id' => Grade::factory()->create()->id,
            'location_id' => Location::factory()->create()->id,
        ], $overrides);
    }

    /** @return array{type: string, hire_date: string, epf_liable: bool, epf_no: string} */
    protected function emp(array $overrides = []): array
    {
        $epf = (string) (900000 + self::$epfSerial++);

        return array_merge([
            'type' => 'PERMANENT',
            'hire_date' => '2026-01-01',
            'epf_liable' => true,
            'epf_no' => $epf,
        ], $overrides);
    }

    protected function hired(string $hireDate = '2026-01-01', ?Company $c = null, array $emp = [], ?Person $p = null): Employment
    {
        $c ??= $this->company();
        $p ??= $this->person();

        return app(EmploymentService::class)->hire(
            $p,
            $c,
            $this->emp(array_merge(['hire_date' => $hireDate], $emp)),
            $this->asg($c),
        );
    }

    /** @return array{0: Bank, 1: BankBranch} */
    protected function bankWithBranch(): array
    {
        $bank = Bank::factory()->create();
        $branch = BankBranch::factory()->create(['bank_id' => $bank->id]);

        return [$bank, $branch];
    }

    /** @return array{effective_from: string, is_cash: bool, bank_id: int, bank_branch_id: int, account_no: string, account_name: string} */
    protected function account(array $overrides = []): array
    {
        [$bank, $branch] = $this->bankWithBranch();

        return array_merge([
            'effective_from' => '2026-01-01',
            'is_cash' => false,
            'bank_id' => $bank->id,
            'bank_branch_id' => $branch->id,
            'account_no' => '0012345678',
            'account_name' => 'N Perera',
        ], $overrides);
    }
}
