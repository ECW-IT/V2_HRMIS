<?php

namespace Modules\Payroll\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Payroll\Enums\CalcMethod;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Models\PayComponent;

class PayComponentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->systemComponents() as $row) {
            $this->upsert($row, isSystem: true);
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = require __DIR__.'/data/pay_components.php';

        foreach ($rows as $row) {
            $this->upsert($row, isSystem: false);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsert(array $row, bool $isSystem): void
    {
        $code = $row['code'];
        unset($row['code']);

        $values = [
            ...$row,
            'is_system' => $isSystem,
            'fund' => $row['fund'] ?? null,
            'night_base_hours' => $row['night_base_hours'] ?? null,
            'epf_liable' => (bool) ($row['epf_liable'] ?? false),
            'taxable' => (bool) ($row['taxable'] ?? false),
        ];

        $existing = PayComponent::query()->where('code', $code)->first();

        if ($existing !== null) {
            unset($values['is_active']);
            $existing->fill($values);
            $existing->save();

            return;
        }

        PayComponent::query()->create([
            'code' => $code,
            'is_active' => true,
            ...$values,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function systemComponents(): array
    {
        return [
            [
                'code' => 'BASIC',
                'name' => 'Basic salary',
                'kind' => ComponentKind::EARNING,
                'method' => CalcMethod::EARNED_BASIC,
                'sheet' => 1,
                'epf_liable' => true,
                'taxable' => true,
                'display_order' => 10,
            ],
            [
                'code' => 'OT',
                'name' => 'Overtime',
                'kind' => ComponentKind::EARNING,
                'method' => CalcMethod::OT,
                'sheet' => 1,
                'epf_liable' => false,
                'taxable' => true,
                'display_order' => 20,
            ],
            [
                'code' => 'EXTRA_DAY',
                'name' => 'Extra day payment',
                'kind' => ComponentKind::EARNING,
                'method' => CalcMethod::EXTRA_DAY,
                'sheet' => 1,
                'epf_liable' => false,
                'taxable' => true,
                'display_order' => 30,
            ],
            [
                'code' => 'ATT_ALLOW',
                'name' => 'Attendance allowance',
                'kind' => ComponentKind::EARNING,
                'method' => CalcMethod::ATTENDANCE_TIER,
                'sheet' => 1,
                'epf_liable' => false,
                'taxable' => true,
                'display_order' => 40,
            ],
            [
                'code' => 'LATE',
                'name' => 'Late deduction',
                'kind' => ComponentKind::DEDUCTION,
                'method' => CalcMethod::LATE,
                'sheet' => 1,
                'epf_liable' => false,
                'taxable' => false,
                'display_order' => 500,
            ],
            [
                'code' => 'EPF_EE',
                'name' => 'EPF (employee 8%)',
                'kind' => ComponentKind::DEDUCTION,
                'method' => CalcMethod::EPF_EMPLOYEE,
                'sheet' => 1,
                'epf_liable' => false,
                'taxable' => false,
                'display_order' => 510,
            ],
            [
                'code' => 'APIT',
                'name' => 'APIT (tax)',
                'kind' => ComponentKind::DEDUCTION,
                'method' => CalcMethod::TAX,
                'sheet' => 1,
                'epf_liable' => false,
                'taxable' => false,
                'display_order' => 520,
            ],
            [
                'code' => 'CF',
                'name' => 'Carried forward',
                'kind' => ComponentKind::DEDUCTION,
                'method' => CalcMethod::CARRIED_FORWARD,
                'sheet' => 1,
                'epf_liable' => false,
                'taxable' => false,
                'display_order' => 530,
            ],
            [
                'code' => 'ADVANCE',
                'name' => 'Salary advance',
                'kind' => ComponentKind::DEDUCTION,
                'method' => CalcMethod::ADVANCE,
                'sheet' => 2,
                'epf_liable' => false,
                'taxable' => false,
                'display_order' => 540,
            ],
            [
                'code' => 'LOAN',
                'name' => 'Loan instalment',
                'kind' => ComponentKind::DEDUCTION,
                'method' => CalcMethod::LOAN,
                'sheet' => 1,
                'epf_liable' => false,
                'taxable' => false,
                'display_order' => 550,
            ],
            [
                'code' => 'EPF_ER',
                'name' => 'EPF (employer 12%)',
                'kind' => ComponentKind::EMPLOYER,
                'method' => CalcMethod::EPF_EMPLOYER,
                'sheet' => 1,
                'epf_liable' => false,
                'taxable' => false,
                'display_order' => 900,
            ],
            [
                'code' => 'ETF_ER',
                'name' => 'ETF (employer 3%)',
                'kind' => ComponentKind::EMPLOYER,
                'method' => CalcMethod::ETF,
                'sheet' => 1,
                'epf_liable' => false,
                'taxable' => false,
                'display_order' => 910,
            ],
        ];
    }
}
