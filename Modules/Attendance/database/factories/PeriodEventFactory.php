<?php

namespace Modules\Attendance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Models\PeriodEvent;
use Modules\Calendar\Services\PayPeriodService;
use Modules\Organisation\Models\Company;

/**
 * @extends Factory<PeriodEvent>
 */
class PeriodEventFactory extends Factory
{
    protected $model = PeriodEvent::class;

    public function definition(): array
    {
        $company = Company::factory()->create();
        $period = app(PayPeriodService::class)->ensure($company, 2026, 12);

        return [
            'pay_period_id' => $period->id,
            'event' => 'CLOSED',
            'version' => 1,
            'reason' => null,
            'user_id' => User::factory(),
            'created_at' => now(),
        ];
    }
}
