<?php

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Enums\RejectionReason;
use Modules\Attendance\Models\Device;
use Modules\Attendance\Models\PunchRejection;

/**
 * @extends Factory<PunchRejection>
 */
class PunchRejectionFactory extends Factory
{
    protected $model = PunchRejection::class;

    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'payload' => ['device_user_id' => 1],
            'reason' => RejectionReason::MALFORMED,
            'received_at' => now(),
        ];
    }
}
