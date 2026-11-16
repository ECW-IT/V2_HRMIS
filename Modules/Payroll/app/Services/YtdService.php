<?php

namespace Modules\Payroll\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Exceptions\InvalidYtdOpening;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunEmployee;
use Modules\Payroll\Models\YtdOpening;
use Modules\Payroll\Support\TaxYear;
use Modules\Payroll\Ytd\YtdFigures;

final class YtdService
{
    /** Opening row (if any) plus every LOCKED run of the employment whose period ends inside the tax year, up to and including $upTo. */
    public function for(int $employmentId, string $taxYearStart, ?PayPeriod $upTo = null): YtdFigures
    {
        $opening = YtdOpening::query()
            ->where('employment_id', $employmentId)
            ->whereDate('tax_year_start', $taxYearStart)
            ->first();

        $rows = $this->lockedEmployees([$employmentId], $taxYearStart, $upTo);

        return $this->sumFigures($opening, $rows);
    }

    /** [employment_id => YtdFigures] for everyone in the company with any figure in the tax year. */
    public function forCompany(int $companyId, string $taxYearStart): array
    {
        $openings = YtdOpening::query()
            ->where('company_id', $companyId)
            ->whereDate('tax_year_start', $taxYearStart)
            ->get()
            ->keyBy('employment_id');

        $employmentIds = PayRunEmployee::query()
            ->where('company_id', $companyId)
            ->whereHas('payRun', function ($q) use ($taxYearStart) {
                $q->where('status', PayRunStatus::LOCKED)
                    ->whereHas('payPeriod', function ($p) use ($taxYearStart) {
                        $p->whereDate('end_date', '>=', $taxYearStart)
                            ->whereDate('end_date', '<=', TaxYear::endFor($taxYearStart));
                    });
            })
            ->pluck('employment_id')
            ->merge($openings->keys())
            ->unique()
            ->values()
            ->all();

        $result = [];
        foreach ($employmentIds as $employmentId) {
            $employmentId = (int) $employmentId;
            $rows = $this->lockedEmployees([$employmentId], $taxYearStart, null);
            $result[$employmentId] = $this->sumFigures($openings->get($employmentId), $rows);
        }

        return $result;
    }

    public function setOpening(Employment $e, string $taxYearStart, array $figures, User $by): YtdOpening
    {
        if (! $by->can('payroll.config') || ! $this->canAccessCompany($by, (int) $e->company_id)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        Gate::authorize('payroll.config');

        $this->assertFirstOfApril($taxYearStart);

        $monthsCovered = (int) ($figures['months_covered'] ?? -1);
        if ($monthsCovered < 0 || $monthsCovered > 11) {
            throw new InvalidYtdOpening('months_covered must be between 0 and 11.');
        }

        $moneyKeys = ['gross', 'taxable_pay', 'tax', 'epf_employee', 'epf_employer', 'etf'];
        $money = [];
        foreach ($moneyKeys as $key) {
            $money[$key] = $this->nonNegativeMoney($figures[$key] ?? null, $key);
        }

        $this->assertNoLockedRunBeyondOpening($e->id, $taxYearStart, $monthsCovered);

        $opening = YtdOpening::query()->firstOrNew([
            'employment_id' => $e->id,
            'tax_year_start' => $taxYearStart,
        ]);

        $opening->fill([
            'company_id' => $e->company_id,
            'months_covered' => $monthsCovered,
            'gross' => $money['gross'],
            'taxable_pay' => $money['taxable_pay'],
            'tax' => $money['tax'],
            'epf_employee' => $money['epf_employee'],
            'epf_employer' => $money['epf_employer'],
            'etf' => $money['etf'],
            'note' => $figures['note'] ?? null,
            'set_by' => $by->id,
        ]);
        $opening->save();

        return $opening->fresh();
    }

    /**
     * @param  list<int>  $employmentIds
     * @return Collection<int, PayRunEmployee>
     */
    private function lockedEmployees(array $employmentIds, string $taxYearStart, ?PayPeriod $upTo)
    {
        $end = TaxYear::endFor($taxYearStart);

        $query = PayRunEmployee::query()
            ->with('lines')
            ->whereIn('employment_id', $employmentIds)
            ->whereHas('payRun', function ($q) use ($taxYearStart, $end, $upTo) {
                $q->where('status', PayRunStatus::LOCKED)
                    ->whereHas('payPeriod', function ($p) use ($taxYearStart, $end, $upTo) {
                        $p->whereDate('end_date', '>=', $taxYearStart)
                            ->whereDate('end_date', '<=', $end);
                        if ($upTo !== null) {
                            $p->whereDate('end_date', '<=', $upTo->end_date);
                        }
                    });
            });

        return $query->get();
    }

    private function sumFigures(?YtdOpening $opening, $rows): YtdFigures
    {
        $gross = $opening ? (string) $opening->gross : '0.00';
        $taxable = $opening ? (string) $opening->taxable_pay : '0.00';
        $tax = $opening ? (string) $opening->tax : '0.00';
        $epfEe = $opening ? (string) $opening->epf_employee : '0.00';
        $epfEr = $opening ? (string) $opening->epf_employer : '0.00';
        $etf = $opening ? (string) $opening->etf : '0.00';
        $months = $opening ? (int) $opening->months_covered : 0;

        foreach ($rows as $row) {
            $gross = bcadd($gross, bcadd((string) $row->gross_sheet1, (string) $row->gross_sheet2, 2), 2);
            $taxable = bcadd($taxable, (string) $row->taxable_pay, 2);
            $tax = bcadd($tax, $this->lineAmount($row, 'APIT'), 2);
            $epfEe = bcadd($epfEe, $this->lineAmount($row, 'EPF_EE'), 2);
            $epfEr = bcadd($epfEr, $this->lineAmount($row, 'EPF_ER'), 2);
            $etf = bcadd($etf, $this->lineAmount($row, 'ETF_ER'), 2);
            $months++;
        }

        return new YtdFigures(
            $this->money($gross),
            $this->money($taxable),
            $this->money($tax),
            $this->money($epfEe),
            $this->money($epfEr),
            $this->money($etf),
            $months,
        );
    }

    private function lineAmount(PayRunEmployee $row, string $code): string
    {
        $sum = '0.00';
        foreach ($row->lines as $line) {
            if ($line->code === $code) {
                $sum = bcadd($sum, (string) $line->amount, 2);
            }
        }

        return $sum;
    }

    private function assertFirstOfApril(string $taxYearStart): void
    {
        $day = CarbonImmutable::parse($taxYearStart, config('app.timezone'))->startOfDay();
        if ($day->month !== 4 || $day->day !== 1) {
            throw new InvalidYtdOpening('The tax year start must be 1 April.');
        }
    }

    private function assertNoLockedRunBeyondOpening(int $employmentId, string $taxYearStart, int $monthsCovered): void
    {
        $lastCovered = CarbonImmutable::parse($taxYearStart, config('app.timezone'))
            ->addMonths($monthsCovered)
            ->subDay()
            ->toDateString();
        $yearEnd = TaxYear::endFor($taxYearStart);

        $blocked = PayRun::query()
            ->where('status', PayRunStatus::LOCKED)
            ->whereHas('employees', fn ($q) => $q->where('employment_id', $employmentId))
            ->whereHas('payPeriod', function ($p) use ($taxYearStart, $yearEnd, $lastCovered) {
                $p->whereDate('end_date', '>=', $taxYearStart)
                    ->whereDate('end_date', '<=', $yearEnd)
                    ->whereDate('end_date', '>', $lastCovered);
            })
            ->exists();

        if ($blocked) {
            throw new InvalidYtdOpening('An opening cannot be set after a locked pay run in that tax year.');
        }
    }

    private function nonNegativeMoney(mixed $value, string $key): string
    {
        if ($value === null || $value === '') {
            throw new InvalidYtdOpening("{$key} is required.");
        }

        $amount = $this->money((string) $value);
        if (bccomp($amount, '0.00', 2) < 0) {
            throw new InvalidYtdOpening("{$key} cannot be negative.");
        }

        return $amount;
    }

    private function money(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function canAccessCompany(User $by, int $companyId): bool
    {
        if ($by->all_companies) {
            return true;
        }

        return $by->companies()->where('companies.id', $companyId)->exists();
    }
}
