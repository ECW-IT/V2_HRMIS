<?php

namespace Modules\Leave\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\Leave\Enums\LedgerEntryKind;
use Modules\Leave\Models\LeaveLedgerEntry;
use Modules\Leave\Models\LeaveType;
use Modules\Organisation\Models\Company;

/**
 * @extends Factory<LeaveLedgerEntry>
 */
class LeaveLedgerEntryFactory extends Factory
{
    protected $model = LeaveLedgerEntry::class;

    public function definition(): array
    {
        $company = Company::factory()->create();
        $employment = Employment::factory()->create(['company_id' => $company->id]);
        $type = LeaveType::factory()->create();
        $period = app(PayPeriodService::class)->ensure($company, 2026, 12);

        return [
            'employment_id' => $employment->id,
            'leave_type_id' => $type->id,
            'pay_period_id' => $period->id,
            'kind' => LedgerEntryKind::GRANT->value,
            'days' => 8.0,
            'leave_request_id' => null,
            'note' => null,
            'created_by' => null,
            'created_at' => now(),
        ];
    }
}
