<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Workflow\Enums\ApproverType;
use Modules\Workflow\Models\ApprovalDefinition;

class ApprovalDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            ['code' => 'EMPLOYEE_CHANGE', 'name' => 'Employee data change'],
            ['code' => 'EMPLOYEE_EXIT', 'name' => 'Employee exit or transfer'],
        ];

        foreach ($definitions as $row) {
            $definition = ApprovalDefinition::query()->updateOrCreate(
                ['code' => $row['code']],
                ['name' => $row['name'], 'is_active' => true],
            );

            $definition->steps()->delete();
            $definition->steps()->create([
                'sequence' => 1,
                'approver_type' => ApproverType::ROLE,
                'approver_ref' => 'hr-manager',
            ]);
        }
    }
}
