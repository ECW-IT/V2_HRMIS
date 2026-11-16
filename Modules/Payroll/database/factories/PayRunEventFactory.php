<?php

namespace Modules\Payroll\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunEvent;

/**
 * @extends Factory<PayRunEvent>
 */
class PayRunEventFactory extends Factory
{
    protected $model = PayRunEvent::class;

    public function definition(): array
    {
        return [
            'pay_run_id' => PayRun::factory(),
            'event' => 'CALCULATED',
            'note' => null,
            'user_id' => User::factory(),
            'created_at' => now(),
        ];
    }
}
