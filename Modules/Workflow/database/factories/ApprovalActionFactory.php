<?php

namespace Modules\Workflow\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Workflow\Enums\Decision;
use Modules\Workflow\Models\ApprovalAction;
use Modules\Workflow\Models\ApprovalRequest;

/**
 * @extends Factory<ApprovalAction>
 */
class ApprovalActionFactory extends Factory
{
    protected $model = ApprovalAction::class;

    public function definition(): array
    {
        return [
            'approval_request_id' => ApprovalRequest::factory(),
            'step' => 1,
            'user_id' => User::factory(),
            'decision' => Decision::APPROVED,
            'remarks' => null,
            'acted_at' => now(),
        ];
    }
}
