<?php

namespace Modules\Payroll\Run;

use Carbon\CarbonImmutable;
use Modules\Attendance\Services\SummaryService;
use Modules\Calendar\Enums\PeriodStatus;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Readiness\ReadinessService;
use Modules\CoreHr\Readiness\Severity;
use Modules\CoreHr\Services\BankAccountService;
use Modules\Organisation\Models\Company;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Exceptions\InvalidPayRule;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Services\AdvanceService;
use Modules\Payroll\Services\CompensationService;
use Modules\Payroll\Services\PayRuleService;
use Modules\Workflow\Enums\ApprovalStatus;
use Modules\Workflow\Models\ApprovalRequest;

final class PreRunChecker
{
    public function __construct(
        private readonly SummaryService $summaries,
        private readonly PayRuleService $payRules,
        private readonly CompensationService $compensation,
        private readonly AdvanceService $advances,
        private readonly BankAccountService $banks,
        private readonly ReadinessService $readiness,
    ) {}

    /** @return list<PreRunCheck> */
    public function run(PayPeriod $p): array
    {
        $company = Company::query()->findOrFail($p->company_id);
        $end = CarbonImmutable::parse($p->end_date)->toDateString();
        $summaries = $this->summaries->final($p);
        $employmentIds = $summaries->pluck('employment_id')->map(fn ($id) => (int) $id)->all();

        return [
            $this->pr01($p),
            $this->pr02($p),
            $this->pr03($company, $end),
            $this->pr04($summaries, $end),
            $this->pr05($summaries, $end),
            $this->pr06($employmentIds),
            $this->pr07($p, $employmentIds),
            $this->pr08($summaries, $end),
            $this->pr09($p),
            $this->pr10($company, $end),
        ];
    }

    private function pr01(PayPeriod $p): PreRunCheck
    {
        $ok = $p->status === PeriodStatus::CLOSED;
        $count = $ok ? 0 : 1;

        return $this->check('PR01', "The period's time data is not closed", true, $count);
    }

    private function pr02(PayPeriod $p): PreRunCheck
    {
        $hasAnyRun = PayRun::query()->where('company_id', $p->company_id)->exists();
        if (! $hasAnyRun) {
            return $this->check('PR02', "The previous period's pay run is not locked", true, 0);
        }

        $previous = PayPeriod::query()
            ->where('company_id', $p->company_id)
            ->whereDate('start_date', '<', CarbonImmutable::parse($p->start_date)->toDateString())
            ->orderByDesc('start_date')
            ->first();

        if ($previous === null) {
            return $this->check('PR02', "The previous period's pay run is not locked", true, 0);
        }

        $locked = PayRun::query()
            ->where('pay_period_id', $previous->id)
            ->where('status', PayRunStatus::LOCKED)
            ->exists();

        return $this->check('PR02', "The previous period's pay run is not locked", true, $locked ? 0 : 1);
    }

    private function pr03(Company $company, string $end): PreRunCheck
    {
        try {
            $this->payRules->rulesFor($company, $end);

            return $this->check('PR03', 'Pay rules are incomplete', true, 0);
        } catch (InvalidPayRule) {
            return $this->check('PR03', 'Pay rules are incomplete', true, 1);
        }
    }

    private function pr04($summaries, string $end): PreRunCheck
    {
        $ids = [];
        foreach ($summaries as $s) {
            $employment = Employment::query()->find($s->employment_id);
            if ($employment === null) {
                continue;
            }
            $lookup = $this->lookupDate($employment, $end);
            if ($this->compensation->on($employment, $lookup) === null) {
                $ids[] = (int) $employment->id;
            }
        }

        return $this->check('PR04', "Employees without compensation on the period's last day", true, count($ids), $ids);
    }

    private function pr05($summaries, string $end): PreRunCheck
    {
        $categoryIds = $summaries->pluck('employee_category_id')->unique()->filter()->map(fn ($id) => (int) $id)->all();
        $ids = $summaries
            ->filter(fn ($row) => $row->employee_category_id === null)
            ->pluck('employment_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        foreach ($categoryIds as $categoryId) {
            if ($this->payRules->extraDayMethod($categoryId, $end) === null) {
                $ids[] = $categoryId;
            }
        }

        return $this->check('PR05', 'Categories in the run without an extra-day method', true, count($ids), $ids);
    }

    /** @param  list<int>  $employmentIds */
    private function pr06(array $employmentIds): PreRunCheck
    {
        $ids = Employment::query()
            ->whereIn('id', $employmentIds)
            ->where('epf_liable', true)
            ->where(function ($q) {
                $q->whereNull('epf_no')->orWhere('epf_no', '');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->check('PR06', 'EPF-liable employees without an EPF number', true, count($ids), $ids);
    }

    /** @param  list<int>  $employmentIds */
    private function pr07(PayPeriod $p, array $employmentIds): PreRunCheck
    {
        $pending = ApprovalRequest::query()
            ->where('company_id', $p->company_id)
            ->where('status', ApprovalStatus::PENDING)
            ->whereHas('definition', function ($q) {
                $q->whereIn('code', ['COMPENSATION_CHANGE', 'SALARY_ADVANCE', 'LOAN']);
            })
            ->get();

        $ids = [];
        foreach ($pending as $req) {
            if ($req->employment_id !== null && in_array((int) $req->employment_id, $employmentIds, true)) {
                $ids[] = (int) $req->employment_id;
            }
        }
        $ids = array_values(array_unique($ids));

        return $this->check('PR07', 'Employees with a compensation, advance or loan approval still pending', false, count($ids), $ids);
    }

    private function pr08($summaries, string $end): PreRunCheck
    {
        $ids = [];
        foreach ($summaries as $s) {
            $employment = Employment::query()->find($s->employment_id);
            if ($employment === null) {
                continue;
            }
            $lookup = $this->lookupDate($employment, $end);
            $bank = $this->banks->on($employment, $lookup);
            if ($bank === null || (! $bank->is_cash && ($bank->bank_id === null || $bank->account_no === null || $bank->account_no === ''))) {
                if ($bank === null || ! $bank->is_cash) {
                    $ids[] = (int) $employment->id;
                }
            }
        }

        return $this->check('PR08', 'Employees without a bank account who are not marked cash', false, count($ids), $ids);
    }

    private function pr09(PayPeriod $p): PreRunCheck
    {
        $count = $this->advances->approvedUnpaidCount($p);

        return $this->check('PR09', 'Approved advances not yet marked paid', false, $count);
    }

    private function pr10(Company $company, string $end): PreRunCheck
    {
        $issues = $this->readiness->run($company, $end)
            ->filter(fn ($i) => $i->severity === Severity::BLOCKER);
        $ids = $issues->pluck('employmentId')->unique()->values()->all();

        return $this->check('PR10', 'Readiness issues of blocking severity', false, count($ids), $ids);
    }

    /** @param  list<int>  $employmentIds */
    private function check(string $code, string $title, bool $blocking, int $count, array $employmentIds = []): PreRunCheck
    {
        return new PreRunCheck($code, $title, $blocking, $count, $count === 0, $employmentIds);
    }

    private function lookupDate(Employment $e, string $periodEnd): string
    {
        if ($e->exit_date === null) {
            return $periodEnd;
        }

        $exit = CarbonImmutable::parse($e->exit_date)->toDateString();

        return $exit < $periodEnd ? $exit : $periodEnd;
    }
}
