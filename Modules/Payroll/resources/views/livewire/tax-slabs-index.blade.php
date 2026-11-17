<div class="p-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Tax slabs</h1>
            <p class="text-sm text-slate-500">One table for a start date. The last slab has no width.</p>
        </div>
        @if ($canConfigure)
            <button type="button" wire:click="openCreate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">New tax table</button>
        @endif
    </div>

    @forelse ($versions as $from => $rows)
        <div class="mb-4 overflow-hidden rounded bg-white shadow">
            <div class="border-b bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">From {{ $from }}</div>
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Sequence</th>
                        <th class="px-3 py-2">Width</th>
                        <th class="px-3 py-2">Percent</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-t">
                            <td class="px-3 py-2">{{ $row->sequence }}</td>
                            <td class="px-3 py-2">{{ $row->width ?? 'Open' }}</td>
                            <td class="px-3 py-2">{{ $row->percent }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <div class="rounded bg-white px-3 py-6 text-center text-sm text-slate-500 shadow">No tax table yet.</div>
    @endforelse

    @if ($showForm)
        <div class="mt-4 rounded bg-white p-4 shadow">
            @if ($formError)
                <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
            @endif
            <div class="mb-3 max-w-xs">
                <label class="mb-1 block text-xs font-medium text-gray-600">Effective from</label>
                <input type="date" wire:model="effective_from" class="w-full rounded border px-3 py-2 text-sm">
            </div>
            @foreach ($slabs as $index => $slab)
                <div class="mb-2 grid gap-3 sm:grid-cols-2" wire:key="slab-{{ $index }}">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Width</label>
                        <input type="text" wire:model="slabs.{{ $index }}.width" placeholder="Blank on the last slab" class="w-full rounded border px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Percent</label>
                        <input type="text" wire:model="slabs.{{ $index }}.percent" class="w-full rounded border px-3 py-2 text-sm">
                    </div>
                </div>
            @endforeach
            <button type="button" wire:click="addSlab" class="text-sm text-[var(--color-erp-accent)]">Add slab</button>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                <button type="button" wire:click="save" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
            </div>
        </div>
    @endif
</div>
