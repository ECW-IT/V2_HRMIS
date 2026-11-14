<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Attendance\Database\Seeders\ShiftSeeder;
use Modules\CoreHr\Database\Seeders\BankSeeder;
use Modules\CoreHr\Database\Seeders\DocumentTypeSeeder;
use Modules\Leave\Database\Seeders\LeaveSeeder;
use Modules\Organisation\Database\Seeders\StaffWorkCategorySeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            OrganisationSeeder::class,
            StaffWorkCategorySeeder::class,
            ApprovalDefinitionSeeder::class,
            DocumentTypeSeeder::class,
            BankSeeder::class,
            ShiftSeeder::class,
            LeaveSeeder::class,
        ]);
    }
}
