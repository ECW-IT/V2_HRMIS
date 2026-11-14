<?php

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Enums\DecisionLogKind;
use Modules\Attendance\Models\Timecard;
use Modules\Attendance\Models\TimecardDecisionLog;

/**
 * @extends Factory<TimecardDecisionLog>
 */
class TimecardDecisionLogFactory extends Factory
{
    protected $model = TimecardDecisionLog::class;

    public function definition(): array
    {
        $timecard = Timecard::factory()->create();

        return [
            'timecard_id' => $timecard->id,
            'employment_id' => $timecard->employment_id,
            'work_date' => $timecard->work_date,
            'kind' => DecisionLogKind::OT_APPROVED->value,
            'old_value' => null,
            'new_value' => 60,
            'reason' => null,
            'user_id' => null,
            'created_at' => now(),
        ];
    }
}
