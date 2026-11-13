<?php

namespace Modules\CoreHr\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\Assignment;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Grade;
use Modules\Organisation\Models\Job;
use Modules\Organisation\Models\Location;
use Modules\Organisation\Models\OrgUnit;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    public function definition(): array
    {
        return [
            'employment_id' => Employment::factory(),
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'org_unit_id' => OrgUnit::factory(),
            'job_id' => Job::factory(),
            'grade_id' => Grade::factory(),
            'location_id' => Location::factory(),
            'manager_employment_id' => null,
            'change_reason' => null,
        ];
    }
}
