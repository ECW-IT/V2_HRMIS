<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\CoreHr\Database\Seeders\BankSeeder;
use Modules\CoreHr\Database\Seeders\DocumentTypeSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            OrganisationSeeder::class,
            ApprovalDefinitionSeeder::class,
            DocumentTypeSeeder::class,
            BankSeeder::class,
        ]);
    }
}
