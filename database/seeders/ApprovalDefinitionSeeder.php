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
            [
                'code' => 'EMPLOYEE_CHANGE',
                'name' => 'Employee data change',
                'steps' => [
                    ['sequence' => 1, 'approver_type' => ApproverType::ROLE, 'approver_ref' => 'hr-manager'],
                ],
            ],
            [
                'code' => 'EMPLOYEE_EXIT',
                'name' => 'Employee exit or transfer',
                'steps' => [
                    ['sequence' => 1, 'approver_type' => ApproverType::ROLE, 'approver_ref' => 'hr-manager'],
                ],
            ],
            [
                'code' => 'ATTENDANCE_ADJUSTMENT',
                'name' => 'Attendance adjustment',
                'steps' => [
                    ['sequence' => 1, 'approver_type' => ApproverType::ROLE, 'approver_ref' => 'hr-manager'],
                ],
            ],
            [
                'code' => 'LEAVE_REQUEST',
                'name' => 'Leave request',
                'steps' => [
                    ['sequence' => 1, 'approver_type' => ApproverType::MANAGER, 'approver_ref' => null],
                    ['sequence' => 2, 'approver_type' => ApproverType::ROLE, 'approver_ref' => 'hr-manager'],
                ],
            ],
        ];

        foreach ($definitions as $row) {
            $definition = ApprovalDefinition::query()->updateOrCreate(
                ['code' => $row['code']],
                ['name' => $row['name'], 'is_active' => true],
            );

            $definition->steps()->delete();
            foreach ($row['steps'] as $step) {
                $definition->steps()->create([
                    'sequence' => $step['sequence'],
                    'approver_type' => $step['approver_type'],
                    'approver_ref' => $step['approver_ref'],
                ]);
            }
        }
    }
}
