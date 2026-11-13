<?php

namespace Modules\Workflow\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organisation\Models\Company;
use Modules\Workflow\Enums\ApprovalActionType;
use Modules\Workflow\Enums\ApprovalStatus;
use Modules\Workflow\Models\ApprovalDefinition;
use Modules\Workflow\Models\ApprovalRequest;

/**
 * @extends Factory<ApprovalRequest>
 */
class ApprovalRequestFactory extends Factory
{
    protected $model = ApprovalRequest::class;

    public function definition(): array
    {
        return [
            'approval_definition_id' => ApprovalDefinition::factory(),
            'company_id' => Company::factory(),
            'subject_type' => 'Tests\\Support\\Probe\\ScopedProbe',
            'subject_id' => 1,
            'action' => ApprovalActionType::UPDATE,
            'payload' => ['name' => 'B'],
            'status' => ApprovalStatus::PENDING,
            'current_step' => 1,
            'requested_by' => User::factory(),
            'requested_at' => now(),
            'decided_at' => null,
        ];
    }
}
