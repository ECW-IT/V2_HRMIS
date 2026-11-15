<?php

namespace Modules\Payroll\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Payroll\Models\LoanType;

class LoanTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'STAFF', 'name' => 'Staff loan'],
            ['code' => 'UNIFORM', 'name' => 'Uniform'],
        ] as $row) {
            LoanType::query()->updateOrCreate(
                ['code' => $row['code']],
                ['name' => $row['name'], 'is_active' => true],
            );
        }
    }
}
