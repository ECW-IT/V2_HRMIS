<div class="p-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Year-to-date openings</h1>
            <p class="text-sm text-slate-500">Figures already earned in the tax year before this system takes over. The tax year starts on 1 April.</p>
        </div>
        <button type="button" wire:click="openCreate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">New opening</button>
    </div>

    <div class="overflow-hidden rounded bg-white shadow">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2">Employee</th>
                    <th class="px-3 py-2">Tax year</th>
                    <th class="px-3 py-2">Months</th>
                    <th class="px-3 py-2">Gross</th>
                    <th class="px-3 py-2">Tax</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($openings as $opening)
                    <tr class="border-t">
                        <td class="px-3 py-2">{{ $opening->employment?->employee_no }}</td>
                        <td class="px-3 py-2">{{ $opening->tax_year_start->toDateString() }}</td>
                        <td class="px-3 py-2">{{ $opening->months_covered }}</td>
                        <td class="px-3 py-2 font-medium">{{ $opening->gross }}</td>
                        <td class="px-3 py-2">{{ $opening->tax }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">No openings yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="mt-4 rounded bg-white p-4 shadow">
            @if ($formError)
                <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
            @endif
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Employee</label>
                    <select wire:model="employmentId" class="w-full rounded border px-3 py-2 text-sm">
                        <option value="">Choose an employee</option>
                        @foreach ($employments as $employment)
                            <option value="{{ $employment->id }}">{{ $employment->employee_no }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Tax year start</label>
                    <input type="date" wire:model="tax_year_start" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Months covered</label>
                    <input type="number" wire:model="months_covered" min="0" max="11" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Gross</label>
                    <input type="text" wire:model="gross" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Taxable pay</label>
                    <input type="text" wire:model="taxable_pay" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Tax</label>
                    <input type="text" wire:model="tax" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">EPF employee</label>
                    <input type="text" wire:model="epf_employee" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">EPF employer</label>
                    <input type="text" wire:model="epf_employer" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">ETF</label>
                    <input type="text" wire:model="etf" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Note</label>
                    <input type="text" wire:model="note" class="w-full rounded border px-3 py-2 text-sm">
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                <button type="button" wire:click="save" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
            </div>
        </div>
    @endif
</div>
