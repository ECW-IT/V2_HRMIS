<?php

namespace Modules\Payroll\Database\Seeders;

// PLACEHOLDER values — confirm every figure with Finance (08_phase_3_plan.md, P3, P5, P6, P10).

use Illuminate\Database\Seeder;
use Modules\Organisation\Models\Company;
use Modules\Payroll\Models\AttendanceAllowanceTier;
use Modules\Payroll\Models\CompanyPaySetting;
use Modules\Payroll\Models\StatutoryRate;
use Modules\Payroll\Models\TaxSlab;

class PayRuleSeeder extends Seeder
{
    private const FROM = '2026-01-01';

    public function run(): void
    {
        $this->seedStatutory();
        $this->seedTaxTable();
        $this->seedTiers();
        $this->seedCompanySettings();
    }

    private function seedStatutory(): void
    {
        foreach ([
            ['code' => 'EPF_EE', 'percent' => '8.00'],
            ['code' => 'EPF_ER', 'percent' => '12.00'],
            ['code' => 'ETF', 'percent' => '3.00'],
        ] as $row) {
            if (StatutoryRate::query()->where('code', $row['code'])->whereDate('effective_from', self::FROM)->exists()) {
                continue;
            }

            StatutoryRate::query()->create([
                ...$row,
                'effective_from' => self::FROM,
                'effective_to' => null,
            ]);
        }
    }

    private function seedTaxTable(): void
    {
        if (TaxSlab::query()->whereDate('effective_from', self::FROM)->exists()) {
            return;
        }

        $slabs = [
            ['width' => '150000.00', 'percent' => '0.00'],
            ['width' => '83333.33', 'percent' => '6.00'],
            ['width' => '41666.67', 'percent' => '18.00'],
            ['width' => '41666.67', 'percent' => '24.00'],
            ['width' => '41666.67', 'percent' => '30.00'],
            ['width' => null, 'percent' => '36.00'],
        ];

        foreach ($slabs as $index => $slab) {
            TaxSlab::query()->create([
                'effective_from' => self::FROM,
                'effective_to' => null,
                'sequence' => $index + 1,
                'width' => $slab['width'],
                'percent' => $slab['percent'],
            ]);
        }
    }

    private function seedTiers(): void
    {
        foreach ([
            ['code' => 'T1', 'name' => 'Tier 1', 'base_amount' => '5000.00', 'per_day_rate' => '1000.00'],
            ['code' => 'T2', 'name' => 'Tier 2', 'base_amount' => '10000.00', 'per_day_rate' => '1100.00'],
            ['code' => 'T3', 'name' => 'Tier 3', 'base_amount' => '15000.00', 'per_day_rate' => '1200.00'],
            ['code' => 'T4', 'name' => 'Tier 4', 'base_amount' => '20000.00', 'per_day_rate' => '1500.00'],
        ] as $row) {
            if (AttendanceAllowanceTier::query()->where('code', $row['code'])->whereDate('effective_from', self::FROM)->exists()) {
                continue;
            }

            AttendanceAllowanceTier::query()->create([
                ...$row,
                'effective_from' => self::FROM,
                'effective_to' => null,
            ]);
        }
    }

    private function seedCompanySettings(): void
    {
        foreach (Company::query()->get() as $company) {
            if (CompanyPaySetting::query()
                ->where('company_id', $company->id)
                ->whereDate('effective_from', self::FROM)
                ->exists()) {
                continue;
            }

            CompanyPaySetting::query()->create([
                'company_id' => $company->id,
                'late_divisor' => 14400,
                'attendance_min_offset' => '2.0',
                'attendance_max_offset' => '3.0',
                'hours_per_month' => 240,
                'hours_per_day' => 8,
                'ot_multiplier' => '1.50',
                'effective_from' => self::FROM,
                'effective_to' => null,
            ]);
        }
    }
}
