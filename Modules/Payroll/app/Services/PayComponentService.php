<?php

namespace Modules\Payroll\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Modules\Payroll\Enums\CalcMethod;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Enums\FundCode;
use Modules\Payroll\Exceptions\InvalidPayComponent;
use Modules\Payroll\Models\PayComponent;

final class PayComponentService
{
    /** @var list<CalcMethod> */
    private const USER_METHODS = [
        CalcMethod::FIXED,
        CalcMethod::PROPORTIONAL,
        CalcMethod::QUANTITY_RATE,
        CalcMethod::NIGHT_UNITS,
        CalcMethod::ENTRY,
    ];

    public function create(array $data): PayComponent
    {
        Gate::authorize('payroll.config');

        $code = (string) ($data['code'] ?? '');
        $this->assertCodeFormat($code);
        $this->assertCodeUnique($code);

        $kind = $this->kind($data['kind'] ?? null);
        $method = $this->method($data['method'] ?? null);

        if (! in_array($method, self::USER_METHODS, true)) {
            throw new InvalidPayComponent('That calculation method is reserved for system components.');
        }

        $attrs = $this->validatedAttributes($data, $kind, $method);

        return PayComponent::query()->create([
            'code' => $code,
            'name' => (string) $data['name'],
            'kind' => $kind,
            'method' => $method,
            'is_system' => false,
            'is_active' => true,
            ...$attrs,
        ]);
    }

    public function update(PayComponent $c, array $data): PayComponent
    {
        Gate::authorize('payroll.config');

        if (array_key_exists('code', $data) && (string) $data['code'] !== $c->code) {
            throw new InvalidPayComponent('A component code cannot be changed.');
        }

        $kind = array_key_exists('kind', $data) ? $this->kind($data['kind']) : $c->kind;
        $method = array_key_exists('method', $data) ? $this->method($data['method']) : $c->method;

        if ($c->is_system) {
            if ($kind !== $c->kind || $method !== $c->method) {
                throw new InvalidPayComponent('A system component cannot change kind or method.');
            }
        }

        $merged = [
            'name' => $data['name'] ?? $c->name,
            'sheet' => $data['sheet'] ?? $c->sheet,
            'epf_liable' => array_key_exists('epf_liable', $data) ? $data['epf_liable'] : $c->epf_liable,
            'taxable' => array_key_exists('taxable', $data) ? $data['taxable'] : $c->taxable,
            'fund' => array_key_exists('fund', $data) ? $data['fund'] : $c->fund,
            'night_base_hours' => array_key_exists('night_base_hours', $data) ? $data['night_base_hours'] : $c->night_base_hours,
            'display_order' => $data['display_order'] ?? $c->display_order,
        ];

        $attrs = $this->validatedAttributes($merged, $kind, $method);

        $c->update([
            'name' => (string) $merged['name'],
            'kind' => $kind,
            'method' => $method,
            ...$attrs,
        ]);

        return $c->fresh();
    }

    public function deactivate(PayComponent $c): PayComponent
    {
        Gate::authorize('payroll.config');

        if ($c->is_system) {
            throw new InvalidPayComponent('A system component cannot be deactivated.');
        }

        $c->update(['is_active' => false]);

        return $c->fresh();
    }

    /** Active components keyed by code, in display order. */
    public function catalogue(): Collection
    {
        return PayComponent::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('code')
            ->get()
            ->keyBy('code');
    }

    public function byCode(string $code): PayComponent
    {
        $component = PayComponent::query()->where('code', $code)->first();

        if ($component === null) {
            throw new InvalidPayComponent('Unknown pay component code.');
        }

        return $component;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validatedAttributes(array $data, ComponentKind $kind, CalcMethod $method): array
    {
        $sheet = (int) ($data['sheet'] ?? 1);

        if ($kind === ComponentKind::EMPLOYER || $kind === ComponentKind::INFORMATION) {
            $sheet = 1;
        }

        if ($sheet !== 1 && $sheet !== 2) {
            throw new InvalidPayComponent('Sheet must be 1 or 2.');
        }

        $epfLiable = (bool) ($data['epf_liable'] ?? false);
        $taxable = (bool) ($data['taxable'] ?? false);

        if ($sheet === 2 && $epfLiable) {
            throw new InvalidPayComponent('A sheet 2 component cannot be EPF-liable.');
        }

        if (($epfLiable || $taxable) && $kind !== ComponentKind::EARNING) {
            throw new InvalidPayComponent('EPF and tax flags may only be set on earnings.');
        }

        $fund = $this->fund($data['fund'] ?? null);
        if ($fund !== null && ! ($kind === ComponentKind::DEDUCTION && $method === CalcMethod::FIXED)) {
            throw new InvalidPayComponent('A fund may only be set on a fixed deduction.');
        }

        $nightBase = $data['night_base_hours'] ?? null;
        if ($nightBase === '') {
            $nightBase = null;
        }

        if ($method === CalcMethod::NIGHT_UNITS) {
            if ($nightBase === null) {
                throw new InvalidPayComponent('Night units require a base hours value.');
            }
            $nightBase = (int) $nightBase;
            if ($nightBase < 1 || $nightBase > 12) {
                throw new InvalidPayComponent('Night base hours must be between 1 and 12.');
            }
        } elseif ($nightBase !== null) {
            throw new InvalidPayComponent('Night base hours are only allowed for night units.');
        }

        return [
            'sheet' => $sheet,
            'epf_liable' => $epfLiable,
            'taxable' => $taxable,
            'fund' => $fund,
            'night_base_hours' => $method === CalcMethod::NIGHT_UNITS ? (int) $nightBase : null,
            'display_order' => (int) ($data['display_order'] ?? 0),
        ];
    }

    private function assertCodeFormat(string $code): void
    {
        if (preg_match('/^[A-Z][A-Z0-9_]{1,19}$/', $code) !== 1) {
            throw new InvalidPayComponent('That component code is not valid.');
        }
    }

    private function assertCodeUnique(string $code): void
    {
        if (PayComponent::query()->where('code', $code)->exists()) {
            throw new InvalidPayComponent('That component code is already in use.');
        }
    }

    private function kind(mixed $value): ComponentKind
    {
        if ($value instanceof ComponentKind) {
            return $value;
        }

        return ComponentKind::from((string) $value);
    }

    private function method(mixed $value): CalcMethod
    {
        if ($value instanceof CalcMethod) {
            return $value;
        }

        return CalcMethod::from((string) $value);
    }

    private function fund(mixed $value): ?FundCode
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof FundCode) {
            return $value;
        }

        return FundCode::from((string) $value);
    }
}
