<?php

namespace Modules\CoreHr\Services;

use Carbon\CarbonImmutable;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Enums\EventType;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Models\Assignment;
use Modules\CoreHr\Models\EmployeeBankAccount;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\EmploymentEvent;
use Modules\Leave\Models\LeaveRequest;
use Modules\Organisation\Models\Company;
use Modules\Payroll\Models\EmployeeCompensation;
use RuntimeException;

final class HrReportService
{
    public const TYPES = [
        'summary' => 'Employee Summary Sheet',
        'active' => 'Active Employees',
        'inactive' => 'Inactive Employees',
        'resigned' => 'Resigned Employees',
        'recruitment' => 'New Recruitment',
        'history' => 'Employment History',
        'increments' => 'Salary Increment Report',
        'probation' => 'Probation Confirmation List',
        'birthdays' => 'Birthday List',
        'electorate' => 'Electorate List',
        'leave' => 'Leave Applied Report',
    ];

    /**
     * @param  array{
     *   employment_id?: int|null,
     *   date_from?: string|null,
     *   date_to?: string|null,
     *   month?: string|null,
     *   leave_mode?: string|null,
     *   leave_date?: string|null
     * }  $filters
     * @return array{title: string, headings: list<string>, rows: list<list<string|null>>}
     */
    public function run(string $type, Company $company, array $filters = []): array
    {
        if (! array_key_exists($type, self::TYPES)) {
            throw new RuntimeException('Unknown report type.');
        }

        return match ($type) {
            'summary' => $this->summary($company, (int) ($filters['employment_id'] ?? 0)),
            'active' => $this->statusList($company, EmploymentStatus::ACTIVE),
            'inactive' => $this->statusList($company, EmploymentStatus::SUSPENDED),
            'resigned' => $this->resigned($company, (string) ($filters['date_from'] ?? ''), (string) ($filters['date_to'] ?? '')),
            'recruitment' => $this->recruitment($company, (string) ($filters['date_from'] ?? ''), (string) ($filters['date_to'] ?? '')),
            'history' => $this->history($company, (int) ($filters['employment_id'] ?? 0)),
            'increments' => $this->increments($company, (string) ($filters['date_from'] ?? ''), (string) ($filters['date_to'] ?? '')),
            'probation' => $this->probation($company, (string) ($filters['month'] ?? '')),
            'birthdays' => $this->birthdays($company, (string) ($filters['month'] ?? '')),
            'electorate' => $this->electorate($company),
            'leave' => $this->leave($company, $filters),
            default => throw new RuntimeException('Unknown report type.'),
        };
    }

    /** @return array{title: string, headings: list<string>, rows: list<list<string|null>>} */
    private function summary(Company $company, int $employmentId): array
    {
        if ($employmentId < 1) {
            throw new RuntimeException('Select an employee.');
        }

        $e = Employment::query()
            ->where('company_id', $company->id)
            ->with([
                'person',
                'assignments' => fn ($q) => $q->with(['designation', 'orgUnit'])->orderByDesc('effective_from'),
            ])
            ->findOrFail($employmentId);

        $assignment = $e->assignments->first();
        $bank = EmployeeBankAccount::query()
            ->where('employment_id', $e->id)
            ->whereNull('effective_to')
            ->orderByDesc('effective_from')
            ->first();

        $rows = [
            ['Employee no.', $e->employee_no],
            ['Name', $e->person->name_with_initials],
            ['NIC', $e->person->nic],
            ['Status', $e->status instanceof EmploymentStatus ? $e->status->value : (string) $e->status],
            ['Hire date', $e->hire_date?->toDateString()],
            ['Type', $e->type?->value ?? (string) $e->type],
            ['Employee category', $e->employeeCategory?->name],
            ['Designation', $assignment?->designation?->name],
            ['Org unit', $assignment?->orgUnit?->name],
            ['Bank account', $bank?->account_no],
            ['Electorate', $e->person->electorate],
            ['Polling division', $e->person->polling_division],
            ['GN division', $e->person->gn_division],
        ];

        return [
            'title' => self::TYPES['summary'],
            'headings' => ['Field', 'Value'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, headings: list<string>, rows: list<list<string|null>>} */
    private function statusList(Company $company, EmploymentStatus $status): array
    {
        $rows = Employment::query()
            ->where('company_id', $company->id)
            ->where('status', $status)
            ->with('person')
            ->orderBy('employee_no')
            ->get()
            ->map(fn (Employment $e) => [
                $e->employee_no,
                $e->person->name_with_initials,
                $e->hire_date?->toDateString(),
                $e->type?->value ?? (string) $e->type,
                $status->value,
            ])
            ->all();

        $key = $status === EmploymentStatus::ACTIVE ? 'active' : 'inactive';

        return [
            'title' => self::TYPES[$key],
            'headings' => ['Employee no.', 'Name', 'Hire date', 'Type', 'Status'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, headings: list<string>, rows: list<list<string|null>>} */
    private function resigned(Company $company, string $from, string $to): array
    {
        [$from, $to] = $this->requireRange($from, $to);

        $rows = Employment::query()
            ->where('company_id', $company->id)
            ->where('status', EmploymentStatus::EXITED)
            ->where('exit_reason', ExitReason::RESIGNATION)
            ->whereDate('exit_date', '>=', $from)
            ->whereDate('exit_date', '<=', $to)
            ->with('person')
            ->orderBy('exit_date')
            ->get()
            ->map(fn (Employment $e) => [
                $e->employee_no,
                $e->person->name_with_initials,
                $e->hire_date?->toDateString(),
                $e->exit_date?->toDateString(),
                $e->exit_reason?->value,
            ])
            ->all();

        return [
            'title' => self::TYPES['resigned'],
            'headings' => ['Employee no.', 'Name', 'Hire date', 'Exit date', 'Reason'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, headings: list<string>, rows: list<list<string|null>>} */
    private function recruitment(Company $company, string $from, string $to): array
    {
        [$from, $to] = $this->requireRange($from, $to);

        $rows = Employment::query()
            ->where('company_id', $company->id)
            ->whereDate('hire_date', '>=', $from)
            ->whereDate('hire_date', '<=', $to)
            ->with('person')
            ->orderBy('hire_date')
            ->get()
            ->map(fn (Employment $e) => [
                $e->employee_no,
                $e->person->name_with_initials,
                $e->hire_date?->toDateString(),
                $e->type?->value ?? (string) $e->type,
                $e->status instanceof EmploymentStatus ? $e->status->value : (string) $e->status,
            ])
            ->all();

        return [
            'title' => self::TYPES['recruitment'],
            'headings' => ['Employee no.', 'Name', 'Hire date', 'Type', 'Status'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, headings: list<string>, rows: list<list<string|null>>} */
    private function history(Company $company, int $employmentId): array
    {
        if ($employmentId < 1) {
            throw new RuntimeException('Select an employee.');
        }

        $e = Employment::query()->where('company_id', $company->id)->findOrFail($employmentId);

        $eventRows = EmploymentEvent::query()
            ->where('employment_id', $e->id)
            ->orderBy('event_date')
            ->orderBy('id')
            ->get()
            ->map(fn (EmploymentEvent $ev) => [
                $ev->event_date?->toDateString(),
                'Event',
                $ev->type instanceof EventType ? $ev->type->value : (string) $ev->type,
                is_array($ev->details) ? json_encode($ev->details) : null,
            ]);

        $assignmentRows = Assignment::query()
            ->where('employment_id', $e->id)
            ->with(['designation', 'orgUnit'])
            ->orderBy('effective_from')
            ->get()
            ->map(fn (Assignment $a) => [
                $a->effective_from?->toDateString(),
                'Assignment',
                trim(($a->orgUnit?->name ?? '').' / '.($a->designation?->name ?? ''), ' /'),
                $a->effective_to?->toDateString(),
            ]);

        $rows = $eventRows->concat($assignmentRows)
            ->sortBy(fn ($r) => $r[0] ?? '')
            ->values()
            ->all();

        return [
            'title' => self::TYPES['history'],
            'headings' => ['Date', 'Kind', 'Detail', 'Extra'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, headings: list<string>, rows: list<list<string|null>>} */
    private function increments(Company $company, string $from, string $to): array
    {
        [$from, $to] = $this->requireRange($from, $to);

        $rows = EmployeeCompensation::query()
            ->where('company_id', $company->id)
            ->whereDate('effective_from', '>=', $from)
            ->whereDate('effective_from', '<=', $to)
            ->with(['employment.person'])
            ->orderBy('effective_from')
            ->get()
            ->map(fn (EmployeeCompensation $c) => [
                $c->employment?->employee_no,
                $c->employment?->person?->name_with_initials,
                $c->effective_from?->toDateString(),
                number_format((float) $c->basic_salary, 2, '.', ','),
                $c->change_reason,
            ])
            ->all();

        return [
            'title' => self::TYPES['increments'],
            'headings' => ['Employee no.', 'Name', 'Effective from', 'Basic salary', 'Reason'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, headings: list<string>, rows: list<list<string|null>>} */
    private function probation(Company $company, string $month): array
    {
        $month = $this->requireMonth($month);
        $start = CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->endOfMonth();

        $rows = Employment::query()
            ->where('company_id', $company->id)
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($inner) use ($start, $end) {
                    $inner->whereDate('confirmation_due_date', '>=', $start->toDateString())
                        ->whereDate('confirmation_due_date', '<=', $end->toDateString());
                })->orWhere(function ($inner) use ($start, $end) {
                    $inner->whereDate('confirmed_on', '>=', $start->toDateString())
                        ->whereDate('confirmed_on', '<=', $end->toDateString());
                });
            })
            ->with('person')
            ->orderBy('employee_no')
            ->get()
            ->map(fn (Employment $e) => [
                $e->employee_no,
                $e->person->name_with_initials,
                $e->confirmation_due_date?->toDateString(),
                $e->confirmed_on?->toDateString(),
                $e->type?->value ?? (string) $e->type,
            ])
            ->all();

        return [
            'title' => self::TYPES['probation'],
            'headings' => ['Employee no.', 'Name', 'Confirmation due', 'Confirmed on', 'Type'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, headings: list<string>, rows: list<list<string|null>>} */
    private function birthdays(Company $company, string $month): array
    {
        $month = $this->requireMonth($month);
        $monthNum = (int) substr($month, 5, 2);

        $rows = Employment::query()
            ->where('company_id', $company->id)
            ->where('status', EmploymentStatus::ACTIVE)
            ->whereHas('person', fn ($q) => $q->whereMonth('date_of_birth', $monthNum))
            ->with('person')
            ->orderBy('employee_no')
            ->get()
            ->map(fn (Employment $e) => [
                $e->employee_no,
                $e->person->name_with_initials,
                $e->person->date_of_birth?->format('m-d'),
            ])
            ->all();

        return [
            'title' => self::TYPES['birthdays'],
            'headings' => ['Employee no.', 'Name', 'Birthday (mm-dd)'],
            'rows' => $rows,
        ];
    }

    /** @return array{title: string, headings: list<string>, rows: list<list<string|null>>} */
    private function electorate(Company $company): array
    {
        $rows = Employment::query()
            ->where('company_id', $company->id)
            ->where('status', EmploymentStatus::ACTIVE)
            ->with('person')
            ->orderBy('employee_no')
            ->get()
            ->map(fn (Employment $e) => [
                $e->employee_no,
                $e->person->name_with_initials,
                $e->person->electorate,
                $e->person->polling_division,
                $e->person->gn_division,
            ])
            ->all();

        return [
            'title' => self::TYPES['electorate'],
            'headings' => ['Employee no.', 'Name', 'Electorate', 'Polling division', 'GN division'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{title: string, headings: list<string>, rows: list<list<string|null>>}
     */
    private function leave(Company $company, array $filters): array
    {
        $mode = (string) ($filters['leave_mode'] ?? 'range');
        if ($mode === 'daily') {
            $day = (string) ($filters['leave_date'] ?? '');
            if ($day === '') {
                throw new RuntimeException('Select a date.');
            }
            $from = $to = CarbonImmutable::parse($day)->toDateString();
        } else {
            [$from, $to] = $this->requireRange(
                (string) ($filters['date_from'] ?? ''),
                (string) ($filters['date_to'] ?? ''),
            );
        }

        $rows = LeaveRequest::query()
            ->where('company_id', $company->id)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->with(['employment.person', 'leaveType'])
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map(fn (LeaveRequest $r) => [
                $r->employment?->employee_no,
                $r->employment?->person?->name_with_initials,
                $r->leaveType?->name ?? $r->leaveType?->code,
                $r->date?->toDateString(),
                $r->portion?->value,
                (string) $r->days,
                $r->status?->value,
            ])
            ->all();

        return [
            'title' => self::TYPES['leave'],
            'headings' => ['Employee no.', 'Name', 'Leave type', 'Date', 'Portion', 'Days', 'Status'],
            'rows' => $rows,
        ];
    }

    /** @return array{0: string, 1: string} */
    private function requireRange(string $from, string $to): array
    {
        if ($from === '' || $to === '') {
            throw new RuntimeException('Select a date range.');
        }
        $from = CarbonImmutable::parse($from)->toDateString();
        $to = CarbonImmutable::parse($to)->toDateString();
        if ($to < $from) {
            throw new RuntimeException('The end date cannot be before the start date.');
        }

        return [$from, $to];
    }

    private function requireMonth(string $month): string
    {
        if ($month === '' || preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            throw new RuntimeException('Select a month (YYYY-MM).');
        }

        return $month;
    }
}
