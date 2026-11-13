<div class="p-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Banks</h1>
            <p class="text-sm text-slate-500">Banks available for employee accounts.</p>
        </div>
        @can('settings.manage')
            <button type="button" wire:click="openCreate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">
                New bank
            </button>
        @endcan
    </div>

    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search banks" class="mb-3 w-full max-w-sm rounded border border-slate-300 px-3 py-2 text-sm">

    <div class="overflow-hidden rounded border bg-white">
        <table class="table-compact min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Code</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($banks as $bank)
                    <tr class="border-t">
                        <td class="px-3 py-2 font-mono text-xs font-medium">{{ $bank->code }}</td>
                        <td class="px-3 py-2">{{ $bank->name }}</td>
                        <td class="px-3 py-2">{{ $bank->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="space-x-2 whitespace-nowrap px-3 py-2 text-right">
                            <a href="{{ route('settings.bank-branches.index', ['bank' => $bank->id]) }}" class="text-slate-600 hover:underline">Branches</a>
                            @can('settings.manage')
                                <button type="button" wire:click="openEdit({{ $bank->id }})" class="text-[var(--color-erp-accent)] hover:underline">Edit</button>
                                <button type="button" wire:click="toggleActive({{ $bank->id }})" class="text-slate-600 hover:underline">
                                    {{ $bank->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">No banks found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-4 shadow-xl">
                <h2 class="text-base font-semibold text-slate-800">{{ $editingId ? 'Edit bank' : 'New bank' }}</h2>
                <div class="mt-3 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Code</label>
                        <input type="text" wire:model="code" maxlength="4" class="w-full rounded border px-3 py-2 font-mono text-sm">
                        @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Name</label>
                        <input type="text" wire:model="name" class="w-full rounded border px-3 py-2 text-sm">
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
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
