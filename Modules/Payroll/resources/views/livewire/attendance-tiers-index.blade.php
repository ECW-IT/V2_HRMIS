<div class="p-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Attendance allowance tiers</h1>
            <p class="text-sm text-slate-500">Base amount and per-day rate from the first day of a month.</p>
        </div>
        @if ($canConfigure)
            <button type="button" wire:click="openCreate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">New tier</button>
        @endif
    </div>

    <div class="overflow-hidden rounded bg-white shadow">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2">Code</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Base</th>
                    <th class="px-3 py-2">Per day</th>
                    <th class="px-3 py-2">From</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tiers as $tier)
                    <tr class="border-t">
                        <td class="px-3 py-2 font-medium">{{ $tier->code }}</td>
                        <td class="px-3 py-2">{{ $tier->name }}</td>
                        <td class="px-3 py-2">{{ $tier->base_amount }}</td>
                        <td class="px-3 py-2">{{ $tier->per_day_rate }}</td>
                        <td class="px-3 py-2">{{ $tier->effective_from->toDateString() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">No tiers yet.</td></tr>
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
                    <label class="mb-1 block text-xs font-medium text-gray-600">Code</label>
                    <input type="text" wire:model="code" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Name</label>
                    <input type="text" wire:model="name" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Base amount</label>
                    <input type="text" wire:model="base_amount" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Per day rate</label>
                    <input type="text" wire:model="per_day_rate" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Effective from</label>
                    <input type="date" wire:model="effective_from" class="w-full rounded border px-3 py-2 text-sm">
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                <button type="button" wire:click="save" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
            </div>
        </div>
    @endif
</div>
