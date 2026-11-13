<div class="p-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Bank branches</h1>
            <p class="text-sm text-slate-500">Branches for each bank.</p>
        </div>
        @can('settings.manage')
            <button type="button" wire:click="openCreate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">
                New branch
            </button>
        @endcan
    </div>

    <div class="mb-3 flex flex-wrap gap-3">
        <select wire:model.live="bankFilter" class="rounded border border-slate-300 px-3 py-2 text-sm">
            <option value="">All banks</option>
            @foreach ($banks as $bank)
                <option value="{{ $bank->id }}">{{ $bank->code }} — {{ $bank->name }}</option>
            @endforeach
        </select>
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search branches" class="w-full max-w-sm rounded border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div class="overflow-hidden rounded border bg-white">
        <table class="table-compact min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Bank</th>
                    <th class="px-3 py-2">Code</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($branches as $branch)
                    <tr class="border-t">
                        <td class="px-3 py-2">{{ $branch->bank?->code }} — {{ $branch->bank?->name }}</td>
                        <td class="px-3 py-2 font-mono text-xs font-medium">{{ $branch->code }}</td>
                        <td class="px-3 py-2">{{ $branch->name }}</td>
                        <td class="px-3 py-2">{{ $branch->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="space-x-2 whitespace-nowrap px-3 py-2 text-right">
                            @can('settings.manage')
                                <button type="button" wire:click="openEdit({{ $branch->id }})" class="text-[var(--color-erp-accent)] hover:underline">Edit</button>
                                <button type="button" wire:click="toggleActive({{ $branch->id }})" class="text-slate-600 hover:underline">
                                    {{ $branch->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">No branches found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-4 shadow-xl">
                <h2 class="text-base font-semibold text-slate-800">{{ $editingId ? 'Edit branch' : 'New branch' }}</h2>
                <div class="mt-3 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Bank</label>
                        <select wire:model="bank_id" class="w-full rounded border px-3 py-2 text-sm">
                            <option value="">Select bank</option>
                            @foreach ($banks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->code }} — {{ $bank->name }}</option>
                            @endforeach
                        </select>
                        @error('bank_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Code</label>
                        <input type="text" wire:model="code" maxlength="3" class="w-full rounded border px-3 py-2 font-mono text-sm">
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
