<?php

namespace Modules\Payroll\Reports;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Modules\Organisation\Models\OrgUnit;
use Modules\Organisation\Services\OrgUnitService;
use Modules\Payroll\Enums\PayRunEmployeeStatus;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Exceptions\ReportNotAvailable;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunEmployee;

final class ReportAccess
{
    public function assertCanView(PayRun $run, User $by): void
    {
        if (! $by->can('payroll.view') || ! $this->canAccessCompany($by, (int) $run->company_id)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    public function assertCanExport(PayRun $run, User $by): void
    {
        if (! $by->can('payroll.export') || ! $this->canAccessCompany($by, (int) $run->company_id)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    /** Paysheets, register, carried-forward. */
    public function assertDraftable(PayRun $run): void
    {
        if (! in_array($run->status, [PayRunStatus::CALCULATED, PayRunStatus::APPROVED, PayRunStatus::LOCKED], true)) {
            throw new ReportNotAvailable('This report is not available until the pay run is calculated.');
        }
    }

    /** Payslips, bank list, statutory lists. */
    public function assertApproved(PayRun $run): void
    {
        if (! in_array($run->status, [PayRunStatus::APPROVED, PayRunStatus::LOCKED], true)) {
            throw new ReportNotAvailable('This report is only available for an approved or locked pay run.');
        }
    }

    public function isDraft(PayRun $run): bool
    {
        return $run->status === PayRunStatus::CALCULATED;
    }

    public function draftSuffix(PayRun $run): string
    {
        return $this->isDraft($run) ? ' — DRAFT — not approved' : '';
    }

    /** @return Collection<int, PayRunEmployee> */
    public function okEmployees(PayRun $run, ?int $orgUnitId = null): Collection
    {
        $query = $run->employees()
            ->with(['lines', 'designation'])
            ->where('status', PayRunEmployeeStatus::OK)
            ->orderBy('employee_no');

        if ($orgUnitId !== null) {
            $unit = OrgUnit::query()->findOrFail($orgUnitId);
            $ids = app(OrgUnitService::class)->descendantIds($unit);
            $query->whereIn('org_unit_id', $ids);
        }

        return $query->get();
    }

    /** @return Collection<int, PayRunEmployee> */
    public function errorEmployees(PayRun $run): Collection
    {
        return $run->employees()
            ->where('status', PayRunEmployeeStatus::ERROR)
            ->orderBy('employee_no')
            ->get();
    }

    public function money(string|float|null $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    public function lineAmount(PayRunEmployee $row, string $code): string
    {
        $sum = '0.00';
        foreach ($row->lines as $line) {
            if ($line->code === $code) {
                $sum = bcadd($sum, (string) $line->amount, 2);
            }
        }

        return $this->money($sum);
    }

    public function canAccessCompany(User $by, int $companyId): bool
    {
        if ($by->all_companies) {
            return true;
        }

        return $by->companies()->where('companies.id', $companyId)->exists();
    }
}
