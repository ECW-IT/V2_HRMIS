<?php

namespace Modules\Payroll\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Enums\CalcMethod;
use Modules\Payroll\Enums\PayEntryStatus;
use Modules\Payroll\Exceptions\InvalidPayEntry;
use Modules\Payroll\Imports\PayEntriesImport;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayEntry;

final class PayEntryService
{
    public function __construct(private readonly CompensationService $compensation) {}

    /** $data keys: component_code, quantity?, amount?, reference?, note?. */
    public function add(Employment $e, PayPeriod $p, array $data, User $by): PayEntry
    {
        $this->authorize($by, (int) $e->company_id);
        $this->assertSameCompany($e, $p);
        $this->assertPeriodAcceptsEntries($p);
        $this->assertEmployedInPeriod($e, $p);

        [$component, $quantity, $amount] = $this->validatedFields($e, $p, $data);

        return PayEntry::query()->create([
            'company_id' => $e->company_id,
            'pay_period_id' => $p->id,
            'employment_id' => $e->id,
            'pay_component_id' => $component->id,
            'quantity' => $quantity,
            'amount' => $amount,
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'status' => PayEntryStatus::ACTIVE,
            'created_by' => $by->id,
            'cancelled_by' => null,
            'cancelled_at' => null,
        ]);
    }

    public function cancel(PayEntry $entry, User $by): PayEntry
    {
        $this->authorize($by, (int) $entry->company_id);
        $period = PayPeriod::query()->findOrFail($entry->pay_period_id);
        $this->assertPeriodAcceptsEntries($period);

        if ($entry->status === PayEntryStatus::CANCELLED) {
            return $entry;
        }

        $entry->update([
            'status' => PayEntryStatus::CANCELLED,
            'cancelled_by' => $by->id,
            'cancelled_at' => now(),
        ]);

        return $entry->fresh();
    }

    /** ACTIVE entries of the employment in the period. */
    public function for(int $employmentId, PayPeriod $p): Collection
    {
        return PayEntry::query()
            ->where('employment_id', $employmentId)
            ->where('pay_period_id', $p->id)
            ->where('status', PayEntryStatus::ACTIVE)
            ->orderBy('id')
            ->get();
    }

    /** Reads an Excel file. Returns ['added' => n, 'errors' => [rowNumber => message]]. Adds nothing when there is any error. */
    public function import(PayPeriod $p, string $path, User $by): array
    {
        $this->authorize($by, (int) $p->company_id);
        $this->assertPeriodAcceptsEntries($p);

        $import = new PayEntriesImport;
        Excel::import($import, $path);

        if (count($import->rows) > 2000) {
            throw new InvalidPayEntry('A pay entry file may have at most 2000 rows.');
        }

        $errors = [];
        $prepared = [];

        foreach ($import->rows as $row) {
            $rowNumber = $row['row'];

            try {
                if ($row['employee_no'] === '') {
                    throw new InvalidPayEntry('Employee number is required.');
                }

                $employment = Employment::query()
                    ->where('company_id', $p->company_id)
                    ->where('employee_no', $row['employee_no'])
                    ->first();

                if ($employment === null) {
                    throw new InvalidPayEntry('Unknown employee number.');
                }

                $this->assertEmployedInPeriod($employment, $p);

                $data = [
                    'component_code' => $row['component_code'],
                    'quantity' => $row['quantity'],
                    'amount' => $row['amount'],
                    'reference' => $row['reference'],
                    'note' => $row['note'],
                ];

                [$component, $quantity, $amount] = $this->validatedFields($employment, $p, $data);

                $prepared[] = [
                    'company_id' => $employment->company_id,
                    'pay_period_id' => $p->id,
                    'employment_id' => $employment->id,
                    'pay_component_id' => $component->id,
                    'quantity' => $quantity,
                    'amount' => $amount,
                    'reference' => $row['reference'],
                    'note' => $row['note'],
                    'status' => PayEntryStatus::ACTIVE,
                    'created_by' => $by->id,
                    'cancelled_by' => null,
                    'cancelled_at' => null,
                ];
            } catch (InvalidPayEntry $e) {
                $errors[$rowNumber] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            return ['added' => 0, 'errors' => $errors];
        }

        DB::transaction(function () use ($prepared) {
            foreach ($prepared as $payload) {
                PayEntry::query()->create($payload);
            }
        });

        return ['added' => count($prepared), 'errors' => []];
    }

    /** Stub for Task 33 — refuse when a pay run is APPROVED or LOCKED. */
    private function assertPeriodAcceptsEntries(PayPeriod $p): void
    {
        // Task 33 will enforce pay-run status.
    }

    /** @return array{0: PayComponent, 1: ?string, 2: ?string} */
    private function validatedFields(Employment $e, PayPeriod $p, array $data): array
    {
        $code = strtoupper(trim((string) ($data['component_code'] ?? '')));
        $component = PayComponent::query()->where('code', $code)->first();

        if ($component === null || ! $component->is_active) {
            throw new InvalidPayEntry('Unknown or inactive component.');
        }

        $method = $component->method instanceof CalcMethod
            ? $component->method
            : CalcMethod::from((string) $component->method);

        $quantityRaw = $data['quantity'] ?? null;
        $amountRaw = $data['amount'] ?? null;
        $quantity = $this->nullableMoney($quantityRaw);
        $amount = $this->nullableMoney($amountRaw);

        if ($method === CalcMethod::ENTRY) {
            if ($quantity !== null) {
                throw new InvalidPayEntry('An entry component cannot have a quantity.');
            }
            if ($amount === null || bccomp($amount, '0.00', 2) <= 0) {
                throw new InvalidPayEntry('An entry component needs a positive amount.');
            }

            return [$component, null, $amount];
        }

        if ($method === CalcMethod::QUANTITY_RATE || $method === CalcMethod::NIGHT_UNITS) {
            if ($amount !== null) {
                throw new InvalidPayEntry('A quantity entry cannot have an amount.');
            }
            if ($quantity === null || bccomp($quantity, '0.00', 2) <= 0) {
                throw new InvalidPayEntry('A quantity entry needs a positive quantity.');
            }

            $periodEnd = $this->dateString($p->end_date);
            $hasRate = $this->compensation->componentsOn($e, $periodEnd)
                ->contains(fn ($row) => (int) $row->pay_component_id === (int) $component->id);

            if (! $hasRate) {
                throw new InvalidPayEntry($e->employee_no.' has no rate for '.$component->name.'.');
            }

            return [$component, $quantity, null];
        }

        throw new InvalidPayEntry($component->name.' is not entered per period.');
    }

    private function assertEmployedInPeriod(Employment $e, PayPeriod $p): void
    {
        $start = $this->dateString($p->start_date);
        $end = $this->dateString($p->end_date);
        $hire = $this->dateString($e->hire_date);
        $exit = $e->exit_date !== null ? $this->dateString($e->exit_date) : null;

        if ($hire > $end || ($exit !== null && $exit < $start)) {
            throw new InvalidPayEntry('The employee was not employed in this period.');
        }
    }

    private function assertSameCompany(Employment $e, PayPeriod $p): void
    {
        if ((int) $e->company_id !== (int) $p->company_id) {
            throw new InvalidPayEntry('Employment and pay period must belong to the same company.');
        }
    }

    private function authorize(User $by, int $companyId): void
    {
        if (! $by->can('pay-entry.manage')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if (! $this->canAccessCompany($by, $companyId)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    private function canAccessCompany(User $by, int $companyId): bool
    {
        if ($by->all_companies) {
            return true;
        }

        return $by->companies()->where('companies.id', $companyId)->exists();
    }

    private function nullableMoney(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function dateString(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }
}
