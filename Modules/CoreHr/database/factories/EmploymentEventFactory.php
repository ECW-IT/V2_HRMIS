<?php

namespace Modules\CoreHr\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Enums\EventType;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\EmploymentEvent;

/**
 * @extends Factory<EmploymentEvent>
 */
class EmploymentEventFactory extends Factory
{
    protected $model = EmploymentEvent::class;

    public function definition(): array
    {
        return [
            'employment_id' => Employment::factory(),
            'type' => EventType::HIRE,
            'event_date' => '2026-01-01',
            'details' => null,
            'related_employment_id' => null,
            'approval_request_id' => null,
            'created_by' => null,
        ];
    }
}
