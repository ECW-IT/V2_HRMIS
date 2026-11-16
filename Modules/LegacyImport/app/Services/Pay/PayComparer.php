<?php

namespace Modules\LegacyImport\Services\Pay;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Calendar\Models\PayPeriod;
use Modules\LegacyImport\Exports\PayComparisonExport;
use Modules\LegacyImport\Support\LegacyPayrollConnection;
use Modules\Organisation\Models\Company;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunEmployee;
use Modules\Payroll\Models\PayRunLine;
use RuntimeException;

final class PayComparer
{
    /**
     * @return array{
     *   path: string,
     *   compared: int,
     *   matched: int,
     *   different: int,
     *   only_v2: int,
     *   only_legacy: int
     * }
     */
    public function run(string $periodYm, ?string $companyCode = null): array
    {
        LegacyPayrollConnection::assertConfigured();

        if (! preg_match('/^\d{4}-\d{2}$/', $periodYm)) {
            throw new RuntimeException('Period must be YYYY-MM.');
        }

        if ($companyCode === null || $companyCode === '') {
            throw new RuntimeException('The --company option is required for legacy pay compare.');
        }

        $company = Company::query()->where('code', $companyCode)->first();
        if ($company === null) {
            throw new RuntimeException("Unknown company code: {$companyCode}");
        }

        [$year, $month] = array_map('intval', explode('-', $periodYm));

        $cycle = DB::connection('legacy_payroll')
            ->table('payr_pay_cycles')
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($cycle === null) {
            throw new RuntimeException("No legacy pay cycle for {$periodYm}");
        }

        $legacyRows = DB::connection('legacy_payroll')
            ->table('payr_paysheet_records')
            ->where('cycle_id', $cycle->id)
            ->get()
            ->keyBy(fn ($r) => (string) $r->emp_no);

        $tempNets = DB::connection('legacy_payroll')
            ->table('payr_employee_tempt_net_salaries')
            ->where('year', $year)
            ->where('month', $month)
            ->get()
            ->keyBy(fn ($r) => (string) $r->emp_no);

        $period = PayPeriod::query()
            ->where('company_id', $company->id)
            ->whereYear('start_date', $year)
            ->whereMonth('start_date', $month)
            ->first();

        $v2ByEmp = collect();
        if ($period !== null) {
            $run = PayRun::query()
                ->where('pay_period_id', $period->id)
                ->whereIn('status', [
                    PayRunStatus::CALCULATED,
                    PayRunStatus::APPROVED,
                    PayRunStatus::LOCKED,
                ])
                ->orderByDesc('id')
                ->first();

            if ($run !== null) {
                $basicId = PayComponent::query()->where('code', 'BASIC')->value('id');
                $epfId = PayComponent::query()->where('code', 'EPF_EE')->value('id');

                foreach (PayRunEmployee::query()->where('pay_run_id', $run->id)->get() as $row) {
                    $basic = $basicId
                        ? (string) (PayRunLine::query()
                            ->where('pay_run_employee_id', $row->id)
                            ->where('pay_component_id', $basicId)
                            ->value('amount') ?? '0.00')
                        : '0.00';
                    $epf = $epfId
                        ? (string) (PayRunLine::query()
                            ->where('pay_run_employee_id', $row->id)
                            ->where('pay_component_id', $epfId)
                            ->value('amount') ?? '0.00')
                        : '0.00';
                    $gross = bcadd((string) $row->gross_sheet1, (string) $row->gross_sheet2, 2);
                    $v2ByEmp->put((string) $row->employee_no, [
                        'basic' => number_format((float) $basic, 2, '.', ''),
                        'gross' => number_format((float) $gross, 2, '.', ''),
                        'epf' => number_format((float) $epf, 2, '.', ''),
                        'deductions' => number_format((float) $row->total_deductions, 2, '.', ''),
                        'net' => number_format((float) $row->net_pay, 2, '.', ''),
                    ]);
                }
            }
        }

        $compared = [];
        $onlyV2 = [];
        $onlyLegacy = [];
        $matched = 0;
        $different = 0;

        $allEmpNos = $v2ByEmp->keys()->merge($legacyRows->keys())->unique()->sort()->values();

        foreach ($allEmpNos as $empNo) {
            $v2 = $v2ByEmp->get($empNo);
            $legacy = $legacyRows->get($empNo);

            if ($v2 === null && $legacy !== null) {
                $net = $legacy->net_pay;
                if (($net === null || $net === '') && $tempNets->has($empNo)) {
                    $net = $tempNets->get($empNo)->net_salary;
                }
                $onlyLegacy[] = [$empNo, number_format((float) $net, 2, '.', '')];

                continue;
            }

            if ($v2 !== null && $legacy === null) {
                $onlyV2[] = [$empNo, $v2['net']];

                continue;
            }

            $legacyNet = $legacy->net_pay;
            if (($legacyNet === null || $legacyNet === '') && $tempNets->has($empNo)) {
                $legacyNet = $tempNets->get($empNo)->net_salary;
            }

            $l = [
                'basic' => number_format((float) $legacy->basic_salary, 2, '.', ''),
                'gross' => number_format((float) $legacy->gross, 2, '.', ''),
                'epf' => number_format((float) $legacy->epf_employee, 2, '.', ''),
                'deductions' => number_format((float) $legacy->total_deductions, 2, '.', ''),
                'net' => number_format((float) $legacyNet, 2, '.', ''),
            ];

            $dBasic = bcsub($v2['basic'], $l['basic'], 2);
            $dGross = bcsub($v2['gross'], $l['gross'], 2);
            $dEpf = bcsub($v2['epf'], $l['epf'], 2);
            $dDed = bcsub($v2['deductions'], $l['deductions'], 2);
            $dNet = bcsub($v2['net'], $l['net'], 2);
            $match = abs((float) $dNet) <= 1.0 ? 'YES' : 'NO';
            if ($match === 'YES') {
                $matched++;
            } else {
                $different++;
            }

            $compared[] = [
                $empNo,
                $v2['basic'], $l['basic'], $dBasic,
                $v2['gross'], $l['gross'], $dGross,
                $v2['epf'], $l['epf'], $dEpf,
                $v2['deductions'], $l['deductions'], $dDed,
                $v2['net'], $l['net'], $dNet,
                $match,
            ];
        }

        $relative = "compare/pay-compare-{$company->code}-{$periodYm}.xlsx";
        Excel::store(new PayComparisonExport([
            'compared' => $compared,
            'only_v2' => $onlyV2,
            'only_legacy' => $onlyLegacy,
        ]), $relative, 'private');

        return [
            'path' => Storage::disk('private')->path($relative),
            'compared' => count($compared),
            'matched' => $matched,
            'different' => $different,
            'only_v2' => count($onlyV2),
            'only_legacy' => count($onlyLegacy),
        ];
    }
}
