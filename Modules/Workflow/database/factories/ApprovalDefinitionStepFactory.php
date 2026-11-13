<?php

namespace Modules\Workflow\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Workflow\Enums\ApproverType;
use Modules\Workflow\Models\ApprovalDefinition;
use Modules\Workflow\Models\ApprovalDefinitionStep;

/**
 * @extends Factory<ApprovalDefinitionStep>
 */
class ApprovalDefinitionStepFactory extends Factory
{
    protected $model = ApprovalDefinitionStep::class;

    public function definition(): array
    {
        return [
            'approval_definition_id' => ApprovalDefinition::factory(),
            'sequence' => 1,
            'approver_type' => ApproverType::ROLE,
            'approver_ref' => 'hr-manager',
        ];
    }
}
