<?php

namespace Modules\LegacyImport\Tests\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\CoreHr\Support\Nic;
use Modules\LegacyImport\Services\MappingRepository;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Models\Location;

trait LegacyFixtures
{
    private static int $empSerial = 1;

    private static int $nicSerial = 1;

    private static int $fpSerial = 100;

    private static int $legacyEpfSerial = 1000;

    protected function setUpLegacyFixtures(): void
    {
        $this->ensureLegacySchemas();
        $this->createLegacyTables();
        $this->mappingFiles();
        $this->seedImportTargets();
    }

    protected function ensureLegacySchemas(): void
    {
        if (! app()->environment('testing')) {
            throw new \RuntimeException('LegacyFixtures may only run in testing.');
        }

        $mysql = config('database.connections.mysql');

        foreach (['legacy_hrm' => 'ecw_hrmis_test_lhrm', 'legacy_payroll' => 'ecw_hrmis_test_lpay'] as $connection => $defaultDb) {
            $db = env($connection === 'legacy_hrm' ? 'LEGACY_HRM_DATABASE' : 'LEGACY_PAYROLL_DATABASE', $defaultDb);

            config([
                "database.connections.{$connection}.host" => $mysql['host'],
                "database.connections.{$connection}.port" => $mysql['port'],
                "database.connections.{$connection}.username" => $mysql['username'],
                "database.connections.{$connection}.password" => $mysql['password'],
                "database.connections.{$connection}.database" => $db,
            ]);
            DB::purge($connection);

            DB::connection('mysql')->statement(
                "CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
            DB::purge($connection);
        }
    }

    protected function createLegacyTables(): void
    {
        $hrm = Schema::connection('legacy_hrm');
        $hrm->dropIfExists('employee_attachments');
        $hrm->dropIfExists('emergency_contacts');
        $hrm->dropIfExists('section_employees');
        $hrm->dropIfExists('employees');

        $hrm->create('employees', function ($table) {
            $table->increments('id');
            $table->string('emp_id')->nullable();
            $table->unsignedInteger('emp_fp_id')->nullable();
            $table->string('emp_etfno')->nullable();
            $table->string('emp_name_with_initial')->nullable();
            $table->string('calling_name')->nullable();
            $table->string('emp_first_name')->nullable();
            $table->string('emp_med_name')->nullable();
            $table->string('emp_last_name')->nullable();
            $table->unsignedTinyInteger('emp_status')->nullable();
            $table->date('emp_birthday')->nullable();
            $table->string('emp_gender')->nullable();
            $table->string('emp_marital_status')->nullable();
            $table->date('emp_join_date')->nullable();
            $table->date('emp_permanent_date')->nullable();
            $table->string('emp_national_id')->nullable();
            $table->string('emp_mobile')->nullable();
            $table->string('emp_email')->nullable();
            $table->string('emp_address')->nullable();
            $table->string('emp_address_2')->nullable();
            $table->string('emp_city')->nullable();
            $table->unsignedInteger('emp_department')->nullable();
            $table->unsignedInteger('emp_branch')->nullable();
            $table->unsignedInteger('job_title_id')->nullable();
            $table->unsignedTinyInteger('is_resigned')->default(0);
            $table->unsignedTinyInteger('deleted')->default(0);
        });

        $hrm->create('section_employees', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('employee_id');
            $table->unsignedInteger('section_id');
            $table->string('status')->nullable();
        });

        $hrm->create('emergency_contacts', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('emp_id');
            $table->string('name')->nullable();
            $table->string('relationship')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_no')->nullable();
            $table->unsignedTinyInteger('status')->nullable();
        });

        $hrm->create('employee_attachments', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('emp_id');
            $table->string('emp_ath_file_name')->nullable();
            $table->unsignedInteger('attachment_type')->nullable();
            $table->unsignedTinyInteger('status')->nullable();
        });

        $pay = Schema::connection('legacy_payroll');
        foreach ([
            'payr_advances_deductions',
            'payr_salary_advance_requests',
            'payr_loan_schedules',
            'payr_employee_loans',
            'payr_employee_adjestments',
            'payr_adjustment_rates',
            'payr_adjustment_types',
            'payr_employee_tempt_net_salaries',
            'payr_paysheet_records',
            'payr_pay_cycles',
            'payr__employee_profile',
        ] as $table) {
            $pay->dropIfExists($table);
        }

        $pay->create('payr__employee_profile', function ($table) {
            $table->increments('id');
            $table->string('emp_no')->nullable();
            $table->string('epf_no')->nullable();
            $table->string('bank_account')->nullable();
            $table->decimal('basic_salary', 12, 2)->nullable();
            $table->string('ot_rate_mode', 20)->nullable();
            $table->string('attendance_type', 20)->nullable();
            $table->unsignedTinyInteger('hostal')->default(0);
            $table->unsignedTinyInteger('status')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
        });

        $pay->create('payr_adjustment_types', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('paysheet_type')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
        });

        $pay->create('payr_adjustment_rates', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('adjustment_id');
            $table->decimal('amount', 12, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
        });

        $pay->create('payr_employee_adjestments', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('emp_id');
            $table->unsignedInteger('adju_id');
            $table->decimal('amount', 12, 2)->nullable();
            $table->unsignedTinyInteger('status')->default(1);
        });

        $pay->create('payr_employee_loans', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('employee_id');
            $table->unsignedInteger('loan_type_id')->nullable();
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->unsignedInteger('approved_installments')->nullable();
            $table->unsignedTinyInteger('if_approved')->default(0);
            $table->unsignedTinyInteger('status')->default(0);
            $table->unsignedTinyInteger('paid')->default(0);
        });

        $pay->create('payr_loan_schedules', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('loan_id');
            $table->date('schedule_month')->nullable();
            $table->decimal('schedule_amount', 12, 2)->nullable();
            $table->unsignedTinyInteger('if_paid')->default(0);
            $table->unsignedTinyInteger('is_grace_month')->default(0);
        });

        $pay->create('payr_salary_advance_requests', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('employee_id');
            $table->decimal('amount', 12, 2)->nullable();
            $table->unsignedTinyInteger('is_paid')->default(0);
            $table->date('paid_date')->nullable();
            $table->unsignedTinyInteger('status')->default(0);
            $table->unsignedInteger('converted_to_loan_id')->nullable();
        });

        $pay->create('payr_advances_deductions', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('payr_employee_salary_advance_id');
            $table->decimal('deducted_amount', 12, 2)->nullable();
            $table->date('month')->nullable();
            $table->unsignedTinyInteger('if_paid')->default(0);
        });

        $pay->create('payr_pay_cycles', function ($table) {
            $table->increments('id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('is_locked')->default(0);
        });

        $pay->create('payr_paysheet_records', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('cycle_id');
            $table->string('emp_no')->nullable();
            $table->decimal('basic_salary', 12, 2)->nullable();
            $table->decimal('gross', 12, 2)->nullable();
            $table->decimal('epf_employee', 12, 2)->nullable();
            $table->decimal('total_deductions', 12, 2)->nullable();
            $table->decimal('net_pay', 12, 2)->nullable();
        });

        $pay->create('payr_employee_tempt_net_salaries', function ($table) {
            $table->increments('id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('emp_no')->nullable();
            $table->decimal('net_salary', 12, 2)->nullable();
        });
    }

    protected function seedImportTargets(): void
    {
        if (! Company::query()->where('code', 'CO1')->exists()) {
            Company::factory()->create([
                'code' => 'CO1',
                'name' => 'Company One',
                'employee_no_prefix' => 'ECW',
                'employee_no_padding' => 3,
                'next_employee_seq' => 1,
            ]);
        }
        if (! Company::query()->where('code', 'CO2')->exists()) {
            Company::factory()->create([
                'code' => 'CO2',
                'name' => 'Company Two',
                'employee_no_prefix' => 'ECN',
                'employee_no_padding' => 3,
                'next_employee_seq' => 1,
            ]);
        }

        foreach (['G1', 'G2', 'G3', 'G4'] as $i => $code) {
            if (! EmployeeCategory::query()->where('code', $code)->exists()) {
                EmployeeCategory::factory()->create([
                    'code' => $code,
                    'name' => "Category {$code}",
                    'level' => $i + 1,
                ]);
            }
        }

        foreach ([['NIT', 'Nittambuwa'], ['NEG', 'Negombo']] as [$code, $name]) {
            if (! Location::query()->where('code', $code)->exists()) {
                Location::factory()->create(['code' => $code, 'name' => $name]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $o
     */
    protected function legacyEmployee(array $o = []): int
    {
        $n = self::$empSerial++;
        $includePayroll = ($o['payroll'] ?? true) !== false;
        unset($o['payroll']);

        $payrollEpf = array_key_exists('payroll_epf', $o) ? $o['payroll_epf'] : null;
        $hasPayrollEpf = array_key_exists('payroll_epf', $o);
        unset($o['payroll_epf']);

        $sectionId = $o['section_id'] ?? null;
        unset($o['section_id']);

        $bankAccount = $o['bank_account'] ?? null;
        unset($o['bank_account']);

        if (! isset($o['emp_national_id'])) {
            $o['emp_national_id'] = $this->uniqueNic();
            $nic = Nic::normalise($o['emp_national_id']);
            $o['emp_birthday'] = $o['emp_birthday'] ?? Nic::dateOfBirth($nic)->toDateString();
            $o['emp_gender'] = $o['emp_gender'] ?? (Nic::gender($nic)->value === 'MALE' ? 'Male' : 'Female');
        }

        $epf = $o['emp_etfno'] ?? (string) (self::$legacyEpfSerial++);
        $empId = $o['emp_id'] ?? ('ECW 0'.str_pad((string) $n, 2, '0', STR_PAD_LEFT));
        $fp = array_key_exists('emp_fp_id', $o) ? $o['emp_fp_id'] : (self::$fpSerial++);

        $row = array_merge([
            'emp_id' => $empId,
            'emp_fp_id' => $fp,
            'emp_etfno' => $epf,
            'emp_name_with_initial' => 'A. Test'.$n,
            'calling_name' => 'Test'.$n,
            'emp_first_name' => 'First'.$n,
            'emp_med_name' => null,
            'emp_last_name' => 'Last'.$n,
            'emp_status' => 1,
            'emp_birthday' => $o['emp_birthday'] ?? '1985-12-05',
            'emp_gender' => $o['emp_gender'] ?? 'Male',
            'emp_marital_status' => 'Single',
            'emp_join_date' => '2024-03-01',
            'emp_permanent_date' => '2024-09-01',
            'emp_national_id' => $o['emp_national_id'],
            'emp_mobile' => '0771234567',
            'emp_email' => "test{$n}@example.com",
            'emp_address' => 'Line 1',
            'emp_address_2' => null,
            'emp_city' => 'Colombo',
            'emp_department' => 10,
            'emp_branch' => 1,
            'job_title_id' => 20,
            'is_resigned' => 0,
            'deleted' => 0,
        ], $o);

        $id = (int) DB::connection('legacy_hrm')->table('employees')->insertGetId($row);

        if ($sectionId !== null) {
            DB::connection('legacy_hrm')->table('section_employees')->insert([
                'employee_id' => $id,
                'section_id' => $sectionId,
                'status' => 'active',
            ]);
        }

        if ($includePayroll) {
            $this->legacyProfile([
                'emp_no' => $empId,
                'epf_no' => $hasPayrollEpf ? $payrollEpf : $row['emp_etfno'],
                'branch_id' => $row['emp_branch'],
                'status' => 1,
                'bank_account' => $bankAccount,
            ]);
        }

        $this->appendGradeAssignment($id);

        return $id;
    }

    /**
     * @param  array<string, mixed>  $o
     */
    /**
     * @param  array<string, mixed>  $o
     */
    protected function legacyProfile(array $o = []): int
    {
        return (int) DB::connection('legacy_payroll')->table('payr__employee_profile')->insertGetId(array_merge([
            'emp_no' => 'ECW 001',
            'epf_no' => '1',
            'bank_account' => null,
            'basic_salary' => 60000,
            'ot_rate_mode' => 'calculated',
            'attendance_type' => null,
            'hostal' => 0,
            'status' => 1,
            'branch_id' => 1,
        ], $o));
    }

    /**
     * @param  array<string, mixed>  $o
     */
    protected function legacyAdjustmentType(array $o = []): int
    {
        $row = array_merge([
            'name' => 'Fuel',
            'paysheet_type' => 'earning',
            'status' => 1,
        ], $o);

        if (isset($row['id'])) {
            $id = (int) $row['id'];
            DB::connection('legacy_payroll')->table('payr_adjustment_types')->updateOrInsert(
                ['id' => $id],
                $row
            );

            return $id;
        }

        return (int) DB::connection('legacy_payroll')->table('payr_adjustment_types')->insertGetId($row);
    }

    /**
     * @param  array<string, mixed>  $o
     */
    protected function legacyEmployeeAdjustment(int $profileId, int $typeId, float|string $amount, int $status = 1): int
    {
        return (int) DB::connection('legacy_payroll')->table('payr_employee_adjestments')->insertGetId([
            'emp_id' => $profileId,
            'adju_id' => $typeId,
            'amount' => $amount,
            'status' => $status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $o
     */
    protected function legacyAdjustmentRate(int $typeId, array $o = []): int
    {
        return (int) DB::connection('legacy_payroll')->table('payr_adjustment_rates')->insertGetId(array_merge([
            'adjustment_id' => $typeId,
            'amount' => 3500,
            'start_date' => '2026-01-01',
            'end_date' => null,
            'status' => 1,
        ], $o));
    }

    /**
     * @param  array<string, mixed>  $loan
     * @param  list<array{month: string, amount: float|string, paid?: int}>  $schedule
     */
    protected function legacyLoan(int $profileId, array $loan = [], array $schedule = []): int
    {
        $id = (int) DB::connection('legacy_payroll')->table('payr_employee_loans')->insertGetId(array_merge([
            'employee_id' => $profileId,
            'loan_type_id' => 1,
            'approved_amount' => 60000,
            'approved_installments' => 6,
            'if_approved' => 1,
            'status' => 1,
            'paid' => 1,
        ], $loan));

        foreach ($schedule as $row) {
            DB::connection('legacy_payroll')->table('payr_loan_schedules')->insert([
                'loan_id' => $id,
                'schedule_month' => $row['month'],
                'schedule_amount' => $row['amount'],
                'if_paid' => $row['paid'] ?? 0,
                'is_grace_month' => $row['grace'] ?? 0,
            ]);
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $advance
     * @param  list<array{month: string, amount: float|string, paid?: int}>  $deductions
     */
    protected function legacyAdvance(int $profileId, array $advance = [], array $deductions = []): int
    {
        $id = (int) DB::connection('legacy_payroll')->table('payr_salary_advance_requests')->insertGetId(array_merge([
            'employee_id' => $profileId,
            'amount' => 10000,
            'is_paid' => 1,
            'paid_date' => '2026-11-15',
            'status' => 1,
            'converted_to_loan_id' => null,
        ], $advance));

        foreach ($deductions as $row) {
            DB::connection('legacy_payroll')->table('payr_advances_deductions')->insert([
                'payr_employee_salary_advance_id' => $id,
                'deducted_amount' => $row['amount'],
                'month' => $row['month'],
                'if_paid' => $row['paid'] ?? 0,
            ]);
        }

        return $id;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function legacyPayCycle(int $year, int $month, array $rows = [], bool $locked = true): int
    {
        $cycleId = (int) DB::connection('legacy_payroll')->table('payr_pay_cycles')->insertGetId([
            'year' => $year,
            'month' => $month,
            'is_locked' => $locked ? 1 : 0,
        ]);

        foreach ($rows as $row) {
            DB::connection('legacy_payroll')->table('payr_paysheet_records')->insert(array_merge([
                'cycle_id' => $cycleId,
                'emp_no' => 'ECW 001',
                'basic_salary' => 0,
                'gross' => 0,
                'epf_employee' => 0,
                'total_deductions' => 0,
                'net_pay' => 0,
            ], $row));
        }

        return $cycleId;
    }

    /**
     * @param  array<string, string|null>  $overrides
     */
    protected function mappingFiles(array $overrides = []): void
    {
        $dir = storage_path('app/testing/legacy_import_maps_'.getmypid());
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $defaults = [
            'company_map.csv' => "legacy_branch_id,company_code,location_code\n1,CO1,NIT\n2,CO2,NEG\n",
            'org_unit_map.csv' => "legacy_department_id,legacy_section_id,company_code,org_unit_code\n10,,CO1,PROD\n10,,CO2,PROD\n",
            'job_map.csv' => "legacy_job_title_id,job_code\n20,TECH\n",
            'grade_assignment.csv' => "legacy_hrm_id,grade_code\n",
            'device_groups.csv' => "legacy_branch_id,device_group\n1,NIT\n2,NEG\n",
            'document_type_map.csv' => "legacy_attachment_type_id,document_type_code\n1,OTHER\n",
            'org_units.csv' => "company_code,code,name,type,parent_code\nCO1,PROD,Production,DEPARTMENT,\nCO2,PROD,Production,DEPARTMENT,\n",
            'jobs.csv' => "code,name\nTECH,Technician\n",
            'pay_components.csv' => "legacy_adjustment_type_id,legacy_name,v2_code,action\n1,Fuel,FUEL,COMPONENT\n2,OT Rate,,OT_RATE\n3,Attendance Allowance,ATT_ALLOW,IGNORE\n4,Hostel,HOSTEL,IGNORE\n5,Meals,MEALS,ENTRY\n6,Skip me,,IGNORE\n",
        ];

        $paths = [];
        foreach ($defaults as $file => $content) {
            if (array_key_exists($file, $overrides)) {
                if ($overrides[$file] === null) {
                    $path = $dir.DIRECTORY_SEPARATOR.$file;
                    if (is_file($path)) {
                        unlink($path);
                    }
                    $paths[$file] = $path;

                    continue;
                }
                $content = $overrides[$file];
            }

            $path = $dir.DIRECTORY_SEPARATOR.$file;
            file_put_contents($path, $content);
            $paths[$file] = $path;
        }

        // Keep grade_assignment growing across employees unless fully overridden.
        if (! array_key_exists('grade_assignment.csv', $overrides)) {
            $paths['grade_assignment.csv'] = $dir.DIRECTORY_SEPARATOR.'grade_assignment.csv';
        }

        Config::set('legacy_import.company_map', $paths['company_map.csv'] ?? $dir.'/company_map.csv.missing');
        Config::set('legacy_import.org_unit_map', $paths['org_unit_map.csv'] ?? $dir.'/org_unit_map.csv.missing');
        Config::set('legacy_import.job_map', $paths['job_map.csv'] ?? $dir.'/job_map.csv.missing');
        Config::set('legacy_import.grade_assignment', $paths['grade_assignment.csv'] ?? $dir.'/grade_assignment.csv.missing');
        Config::set('legacy_import.device_groups', $paths['device_groups.csv'] ?? $dir.'/device_groups.csv.missing');
        Config::set('legacy_import.document_type_map', $paths['document_type_map.csv'] ?? $dir.'/document_type_map.csv.missing');
        Config::set('legacy_import.org_units', $paths['org_units.csv'] ?? $dir.'/org_units.csv.missing');
        Config::set('legacy_import.jobs', $paths['jobs.csv'] ?? $dir.'/jobs.csv.missing');
        Config::set('legacy_import.pay_components', $paths['pay_components.csv'] ?? $dir.'/pay_components.csv.missing');
        Config::set('legacy_import.attachment_path', $dir.DIRECTORY_SEPARATOR.'attachments');

        if (! is_dir(config('legacy_import.attachment_path'))) {
            mkdir(config('legacy_import.attachment_path'), 0777, true);
        }

        app(MappingRepository::class)->forget();
    }

    protected function appendGradeAssignment(int $legacyHrmId, string $grade = 'G3'): void
    {
        $path = config('legacy_import.grade_assignment');
        if (! is_string($path) || ! is_file($path)) {
            return;
        }

        file_put_contents($path, "{$legacyHrmId},{$grade}\n", FILE_APPEND);
        app(MappingRepository::class)->forget();
    }

    private function uniqueNic(): string
    {
        // Build a valid 12-digit NIC with unique serial; day 340 = Dec 5 (male).
        $n = self::$nicSerial++;
        $year = 1985;
        $day = 340;
        $serial = str_pad((string) ($n % 10000), 4, '0', STR_PAD_LEFT);

        return sprintf('%04d%03d0%s', $year, $day, $serial);
    }
}
