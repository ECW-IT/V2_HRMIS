<div class="p-4">
    @if (session('error'))
        <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Loan types</h1>
            <p class="text-sm text-slate-500">Codes used when a loan is requested. Deactivate a type instead of deleting it.</p>
        </div>
        @if ($canConfigure)
            <button type="button" wire:click="openCreate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">New loan type</button>
        @endif
    </div>

    <div class="overflow-hidden rounded bg-white shadow">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2">Code</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Active</th>
                    @if ($canConfigure)
                        <th class="px-3 py-2"></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($types as $type)
                    <tr class="border-t">
                        <td class="px-3 py-2 font-medium">{{ $type->code }}</td>
                        <td class="px-3 py-2">{{ $type->name }}</td>
                        <td class="px-3 py-2">{{ $type->is_active ? 'Yes' : 'No' }}</td>
                        @if ($canConfigure)
                            <td class="px-3 py-2 text-right">
                                <button type="button" wire:click="openEdit({{ $type->id }})" class="text-sm text-[var(--color-erp-accent)]">Edit</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">No loan types yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="mt-4 rounded bg-white p-4 shadow">
            @if ($formError)
                <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
            @endif
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Code</label>
                    <input type="text" wire:model="code" @disabled($editingId !== null) class="w-full rounded border px-3 py-2 text-sm disabled:bg-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Name</label>
                    <input type="text" wire:model="name" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                @if ($editingId !== null)
                    <label class="flex items-end gap-2 pb-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="is_active"> Active
                    </label>
                @endif
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                <button type="button" wire:click="save" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
            </div>
        </div>
    @endif
</div>
