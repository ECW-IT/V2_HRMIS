<div class="p-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Roles</h1>
            <p class="text-sm text-slate-500">Create roles and open the permission matrix to assign access.</p>
        </div>
        <button
            type="button"
            wire:click="openCreate"
            class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90"
        >
            New role
        </button>
    </div>

    @error('delete')
        <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</div>
    @enderror

    @if (session('status'))
        <div class="mb-3 rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <input
        type="search"
        wire:model.live.debounce.300ms="search"
        placeholder="Search roles"
        class="mb-3 w-full max-w-sm rounded border border-slate-300 px-3 py-2 text-sm"
    >

    <div class="overflow-hidden rounded border bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Role</th>
                    <th class="px-3 py-2">Permissions</th>
                    <th class="px-3 py-2">Users</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($roles as $role)
                    <tr class="border-t">
                        <td class="px-3 py-2 font-medium">{{ $role->name }}</td>
                        <td class="px-3 py-2 text-slate-600">
                            {{ $role->permissions->count() }} assigned
                        </td>
                        <td class="px-3 py-2">{{ $role->users_count }}</td>
                        <td class="space-x-2 px-3 py-2 text-right whitespace-nowrap">
                            <a
                                href="{{ route('access-control.roles.permissions', $role) }}"
                                class="text-[var(--color-erp-accent)] hover:underline"
                            >
                                Permissions
                            </a>
                            <button type="button" wire:click="openEdit({{ $role->id }})" class="text-slate-700 hover:underline">
                                Edit
                            </button>
                            <button
                                type="button"
                                wire:click="delete({{ $role->id }})"
                                wire:confirm="Delete this role?"
                                class="text-red-600 hover:underline"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-6 text-center text-slate-500">No roles found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-4 shadow-xl">
                <h2 class="text-base font-semibold text-slate-800">{{ $editingId ? 'Edit role' : 'New role' }}</h2>
                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-slate-600">Role name (slug)</label>
                    <input type="text" wire:model="formName" placeholder="e.g. hr-officer" class="w-full rounded border px-3 py-2 text-sm">
                    @error('formName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                    <button type="button" wire:click="save" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
