@php
    $epfDisabled = (int) $sheet === 2 || $kind !== 'EARNING';
    $taxDisabled = $kind !== 'EARNING';
    $fundDisabled = ! ($kind === 'DEDUCTION' && $method === 'FIXED');
    $nightDisabled = $method !== 'NIGHT_UNITS';
    $kindMethodDisabled = $editingIsSystem;
    $codeDisabled = $editingId !== null;
@endphp

<div class="p-4">
    @if (session('error'))
        <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Pay components</h1>
            <p class="text-sm text-slate-500">Catalogue of earnings, deductions and employer charges.</p>
        </div>
        @if ($canConfigure)
            <button type="button" wire:click="openCreate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">
                New component
            </button>
        @endif
    </div>

    <div class="mb-3 flex flex-wrap gap-3 rounded bg-white p-3 shadow">
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Kind</label>
            <select wire:model.live="filterKind" class="rounded border border-gray-300 px-2 py-1.5 text-sm focus:ring-blue-500">
                <option value="">All</option>
                @foreach ($kinds as $k)
                    <option value="{{ $k->value }}">{{ $k->value }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Sheet</label>
            <select wire:model.live="filterSheet" class="rounded border border-gray-300 px-2 py-1.5 text-sm focus:ring-blue-500">
                <option value="">All</option>
                <option value="1">1</option>
                <option value="2">2</option>
            </select>
        </div>
    </div>

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="table-compact min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Code</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Kind</th>
                    <th class="px-3 py-2">Method</th>
                    <th class="px-3 py-2">Sheet</th>
                    <th class="px-3 py-2">EPF</th>
                    <th class="px-3 py-2">Taxable</th>
                    <th class="px-3 py-2">Fund</th>
                    <th class="px-3 py-2">Active</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($components as $component)
                    <tr class="border-t hover:bg-[var(--color-erp-row-hover)]" @if($component->is_system) data-system="1" @endif>
                        <td class="px-3 py-2 font-medium">
                            {{ $component->code }}
                            @if ($component->is_system)
                                <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] uppercase text-slate-600">System</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ $component->name }}</td>
                        <td class="px-3 py-2">{{ $component->kind instanceof \Modules\Payroll\Enums\ComponentKind ? $component->kind->value : $component->kind }}</td>
                        <td class="px-3 py-2">{{ $component->method instanceof \Modules\Payroll\Enums\CalcMethod ? $component->method->value : $component->method }}</td>
                        <td class="px-3 py-2">{{ $component->sheet }}</td>
                        <td class="px-3 py-2">{{ $component->epf_liable ? 'Yes' : 'No' }}</td>
                        <td class="px-3 py-2">{{ $component->taxable ? 'Yes' : 'No' }}</td>
                        <td class="px-3 py-2">{{ $component->fund instanceof \Modules\Payroll\Enums\FundCode ? $component->fund->value : ($component->fund ?? '—') }}</td>
                        <td class="px-3 py-2">{{ $component->is_active ? 'Yes' : 'No' }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            @if ($canConfigure)
                                <div class="inline-flex items-center gap-1.5">
                                    <x-action-icon-button variant="edit" wire:click="openEdit({{ $component->id }})" />
                                    @if (! $component->is_system && $component->is_active)
                                        <x-action-icon-button variant="deactivate" wire:click="deactivate({{ $component->id }})" />
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-3 py-6 text-center text-slate-500">No components found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="popup w-full max-w-lg rounded-lg bg-white p-4 shadow-xl">
                <h2 class="text-base font-semibold text-slate-800">{{ $editingId ? 'Edit component' : 'New component' }}</h2>

                @if ($formError)
                    <div class="mt-2 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
                @endif

                <div class="mt-3 grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-600">Code</label>
                        <input type="text" wire:model="code" @disabled($codeDisabled) class="w-full rounded border px-3 py-2 text-sm disabled:bg-slate-100">
                        @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-600">Name</label>
                        <input type="text" wire:model="name" class="w-full rounded border px-3 py-2 text-sm">
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Kind</label>
                        <select wire:model.live="kind" @disabled($kindMethodDisabled) class="w-full rounded border px-3 py-2 text-sm disabled:bg-slate-100">
                            @foreach ($kinds as $k)
                                <option value="{{ $k->value }}">{{ $k->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Method</label>
                        <select wire:model.live="method" @disabled($kindMethodDisabled) class="w-full rounded border px-3 py-2 text-sm disabled:bg-slate-100">
                            @foreach ($methods as $m)
                                <option value="{{ $m->value }}">{{ $m->value }}</option>
                            @endforeach
                            @if ($editingIsSystem)
                                <option value="{{ $method }}">{{ $method }}</option>
                            @endif
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Sheet</label>
                        <select wire:model.live="sheet" class="w-full rounded border px-3 py-2 text-sm">
                            <option value="1">1</option>
                            <option value="2">2</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Display order</label>
                        <input type="number" wire:model="display_order" min="0" class="w-full rounded border px-3 py-2 text-sm">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="epf_liable" @disabled($epfDisabled)> EPF liable
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="taxable" @disabled($taxDisabled)> Taxable
                    </label>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Fund</label>
                        <select wire:model="fund" @disabled($fundDisabled) class="w-full rounded border px-3 py-2 text-sm disabled:bg-slate-100">
                            <option value="">None</option>
                            @foreach ($funds as $f)
                                <option value="{{ $f->value }}">{{ $f->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Night base hours</label>
                        <input type="number" wire:model="night_base_hours" min="1" max="12" @disabled($nightDisabled) class="w-full rounded border px-3 py-2 text-sm disabled:bg-slate-100">
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                    <button type="button" wire:click="save" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
