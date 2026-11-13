<div class="p-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Grades</h1>
            <p class="text-sm text-slate-500">Pay and seniority grades ordered by rank.</p>
        </div>
        @can('org.manage')
            <button type="button" wire:click="openCreate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">
                New grade
            </button>
        @endcan
    </div>

    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search grades" class="mb-3 w-full max-w-sm rounded border border-slate-300 px-3 py-2 text-sm">

    <div class="overflow-hidden rounded border bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Rank</th>
                    <th class="px-3 py-2">Code</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($grades as $grade)
                    <tr class="border-t">
                        <td class="px-3 py-2">{{ $grade->rank }}</td>
                        <td class="px-3 py-2 font-medium">{{ $grade->code }}</td>
                        <td class="px-3 py-2">{{ $grade->name }}</td>
                        <td class="px-3 py-2">{{ $grade->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-3 py-2 text-right space-x-2 whitespace-nowrap">
                            @can('org.manage')
                                <button type="button" wire:click="openEdit({{ $grade->id }})" class="text-[var(--color-erp-accent)] hover:underline">Edit</button>
                                <button type="button" wire:click="toggleActive({{ $grade->id }})" class="text-slate-600 hover:underline">
                                    {{ $grade->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">No grades found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-4 shadow-xl">
                <h2 class="text-base font-semibold text-slate-800">{{ $editingId ? 'Edit grade' : 'New grade' }}</h2>
                <div class="mt-3 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Code</label>
                        <input type="text" wire:model="code" class="w-full rounded border px-3 py-2 text-sm">
                        @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Name</label>
                        <input type="text" wire:model="name" class="w-full rounded border px-3 py-2 text-sm">
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Rank</label>
                        <input type="number" wire:model="rank" min="1" class="w-full rounded border px-3 py-2 text-sm">
                        @error('rank') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="is_active"> Active
                    </label>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                    <button type="button" wire:click="save" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
