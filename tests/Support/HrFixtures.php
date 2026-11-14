<?php

namespace Tests\Support;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Attendance\Enums\AttendanceMode;
use Modules\Attendance\Enums\OtMode;
use Modules\Attendance\Models\Shift;
use Modules\Attendance\Models\ShiftBreak;
use Modules\Attendance\Services\TimeProfileService;
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

    /** Factory shift D0800 values unless overridden: 08:00 / 17:00 / 08:06 / 10:00 / 12:30 / 15:00, with an unpaid Dinner break 20:00–20:30. */
    protected function shift(array $o = []): Shift
    {
        $breaks = $o['breaks'] ?? [['name' => 'Dinner', 'start_time' => '20:00', 'end_time' => '20:30', 'is_paid' => false]];
        unset($o['breaks']);

        $shift = Shift::factory()->create(array_merge([
            'code' => 'D0800'.fake()->unique()->numerify('##'),
            'name' => 'Day 08:00–17:00',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'late_from' => '08:06:00',
            'morning_cutoff' => '10:00:00',
            'half_day_from' => '12:30:00',
            'early_leave_from' => '15:00:00',
            'is_active' => true,
        ], $o));

        foreach ($breaks as $break) {
            ShiftBreak::query()->create([
                'shift_id' => $shift->id,
                'name' => $break['name'],
                'start_time' => $break['start_time'],
                'end_time' => $break['end_time'],
                'is_paid' => (bool) ($break['is_paid'] ?? false),
            ]);
        }

        return $shift->fresh(['breaks']);
    }

    /** hired() + time profile from the hire date. $profile keys override: shift_id, attendance_mode, ot_mode, late_deduction (defaults: new shift(), PUNCH, NONE, false). */
    protected function profiled(string $hireDate = '2026-01-01', ?Company $c = null, array $profile = [], array $emp = []): Employment
    {
        $e = $this->hired($hireDate, $c, $emp);
        $shiftId = $profile['shift_id'] ?? $this->shift()->id;

        app(TimeProfileService::class)->set($e, [
            'effective_from' => $hireDate,
            'shift_id' => $shiftId,
            'attendance_mode' => $profile['attendance_mode'] ?? AttendanceMode::PUNCH,
            'ot_mode' => $profile['ot_mode'] ?? OtMode::NONE,
            'late_deduction' => $profile['late_deduction'] ?? false,
        ]);

        return $e->fresh();
    }
}
