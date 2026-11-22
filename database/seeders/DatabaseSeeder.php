<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Attendance\Database\Seeders\DeviceGroupSeeder;
use Modules\Attendance\Database\Seeders\ShiftSeeder;
use Modules\CoreHr\Database\Seeders\BankSeeder;
use Modules\CoreHr\Database\Seeders\DocumentTypeSeeder;
use Modules\CoreHr\Database\Seeders\RegionSeeder;
use Modules\CoreHr\Database\Seeders\RelationshipSeeder;
use Modules\Leave\Database\Seeders\LeaveSeeder;
use Modules\Organisation\Database\Seeders\DesignationsSeeder;
use Modules\Organisation\Database\Seeders\EmployeeCategoriesSeeder;
use Modules\Organisation\Database\Seeders\OrgUnitsSeeder;
use Modules\Organisation\Database\Seeders\StaffWorkCategorySeeder;
use Modules\Payroll\Database\Seeders\LoanTypeSeeder;
use Modules\Payroll\Database\Seeders\PayComponentSeeder;
use Modules\Payroll\Database\Seeders\PayRuleSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            OrganisationSeeder::class,
            OrgUnitsSeeder::class,
            DesignationsSeeder::class,
            EmployeeCategoriesSeeder::class,
            StaffWorkCategorySeeder::class,
            ApprovalDefinitionSeeder::class,
            DocumentTypeSeeder::class,
            RelationshipSeeder::class,
            RegionSeeder::class,
            BankSeeder::class,
            ShiftSeeder::class,
            DeviceGroupSeeder::class,
            LeaveSeeder::class,
            PayComponentSeeder::class,
            PayRuleSeeder::class,
            LoanTypeSeeder::class,
        ]);
    }
}
