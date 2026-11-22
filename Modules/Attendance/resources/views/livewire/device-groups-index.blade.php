<div class="mx-auto max-w-6xl px-6 py-8">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Device groups</h1>
            <p class="text-sm text-slate-500">Fingerprint site groups used on devices and enrolments.</p>
        </div>
        <button type="button" wire:click="openCreate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">
            New device group
        </button>
    </div>

    <div class="overflow-hidden rounded border bg-white">
        <table class="table-compact min-w-full text-left">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Code</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Active</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($groups as $group)
                    <tr class="border-t" wire:key="device-group-{{ $group->id }}">
                        <td class="px-3 py-2 font-medium">{{ $group->code }}</td>
                        <td class="px-3 py-2">{{ $group->name }}</td>
                        <td class="px-3 py-2">{{ $group->is_active ? 'Yes' : 'No' }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5">
                                <x-action-icon-button variant="edit" wire:click="openEdit({{ $group->id }})" />
                                @if ($group->is_active)
                                    <x-action-icon-button variant="deactivate" wire:click="toggleActive({{ $group->id }})" />
                                @else
                                    <x-action-icon-button variant="activate" wire:click="toggleActive({{ $group->id }})" />
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="4" class="px-3 py-4 text-sm text-slate-500">No device groups yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="popup w-full max-w-md rounded border bg-white p-4 shadow-lg">
                <h2 class="mb-3 text-sm font-semibold">{{ $editingId ? 'Edit device group' : 'New device group' }}</h2>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">Code</label>
                        <input type="text" wire:model="code" maxlength="20" class="w-full rounded border px-3 py-1.5 text-sm" @disabled($editingId !== null)>
                        @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">Name</label>
                        <input type="text" wire:model="name" maxlength="100" class="w-full rounded border px-3 py-1.5 text-sm">
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
