<div class="mx-auto max-w-6xl px-6 py-8">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Punches</h1>
            <p class="text-sm text-slate-500">Fingerprint punches stored from the terminals.</p>
        </div>
        @if ($canAdjust)
            <button type="button" wire:click="openCreate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">
                Add punch
            </button>
        @endif
    </div>

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <label class="text-xs text-slate-500">From
            <input type="date" wire:model.live="from" class="mt-1 block rounded border px-3 py-1.5 text-sm">
        </label>
        <label class="text-xs text-slate-500">To
            <input type="date" wire:model.live="to" class="mt-1 block rounded border px-3 py-1.5 text-sm">
        </label>
        <label class="text-xs text-slate-500">Device
            <select wire:model.live="deviceId" class="mt-1 block rounded border px-3 py-1.5 text-sm">
                <option value="">All</option>
                @foreach ($devices as $device)
                    <option value="{{ $device->id }}">{{ $device->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs text-slate-500">Status
            <select wire:model.live="resolution" class="mt-1 block rounded border px-3 py-1.5 text-sm">
                <option value="">All</option>
                <option value="RESOLVED">Resolved</option>
                <option value="UNRESOLVED">Unresolved</option>
            </select>
        </label>
        <label class="text-xs text-slate-500">Search
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Employee, name, or device user ID" class="mt-1 block w-64 rounded border px-3 py-1.5 text-sm">
        </label>
    </div>

    <div class="overflow-hidden rounded border bg-white">
        <table class="table-compact min-w-full text-left">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Employee no.</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Device user ID</th>
                    <th class="px-3 py-2">Date</th>
                    <th class="px-3 py-2">Time</th>
                    <th class="px-3 py-2">Device</th>
                    <th class="px-3 py-2">Status</th>
                    @if ($canAdjust)
                        <th class="px-3 py-2"></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($punches as $punch)
                    <tr class="border-t" wire:key="punch-{{ $punch->id }}">
                        <td class="px-3 py-2">{{ $punch->employment?->employee_no }}</td>
                        <td class="px-3 py-2">{{ $punch->employment?->person?->name_with_initials }}</td>
                        <td class="px-3 py-2 font-medium">{{ $punch->device_user_id }}</td>
                        <td class="px-3 py-2">{{ $punch->punched_at->format('Y-m-d') }}</td>
                        <td class="px-3 py-2">{{ $punch->punched_at->format('H:i:s') }}</td>
                        <td class="px-3 py-2">{{ $punch->device?->name }}</td>
                        <td class="px-3 py-2">{{ $punch->resolution === \Modules\Attendance\Enums\PunchResolution::RESOLVED ? 'Resolved' : 'Unresolved' }}</td>
                        @if ($canAdjust)
                            <td class="px-3 py-2 text-right">
                                <button type="button" wire:click="openEdit({{ $punch->id }})" class="text-blue-700 hover:underline">Edit</button>
                                <button type="button" wire:click="deletePunch({{ $punch->id }})" wire:confirm="Delete this punch?" class="ml-3 text-red-700 hover:underline">Delete</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="{{ $canAdjust ? 8 : 7 }}" class="px-3 py-4 text-sm text-slate-500">No punches for this filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $punches->links() }}
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="popup w-full max-w-md rounded border bg-white p-4 shadow-lg">
                <h2 class="mb-3 text-sm font-semibold">{{ $editingId === null ? 'Add punch' : 'Edit punch' }}</h2>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">Device</label>
                        <select wire:model="formDeviceId" class="w-full rounded border px-3 py-1.5 text-sm">
                            <option value="">Select</option>
                            @foreach ($devices as $device)
                                <option value="{{ $device->id }}">{{ $device->name }}</option>
                            @endforeach
                        </select>
                        @error('formDeviceId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">Fingerprint id</label>
                        <input type="number" min="1" wire:model="formDeviceUserId" class="w-full rounded border px-3 py-1.5 text-sm">
                        @error('formDeviceUserId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs text-slate-500">Date</label>
                            <input type="date" wire:model="formDate" class="w-full rounded border px-3 py-1.5 text-sm">
                            @error('formDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-slate-500">Time</label>
                            <input type="time" step="1" wire:model="formTime" class="w-full rounded border px-3 py-1.5 text-sm">
                            @error('formTime') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs text-slate-500">Verify mode</label>
                            <input type="number" min="0" max="255" wire:model="formVerifyMode" class="w-full rounded border px-3 py-1.5 text-sm">
                            @error('formVerifyMode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-slate-500">State</label>
                            <input type="number" min="0" max="255" wire:model="formState" class="w-full rounded border px-3 py-1.5 text-sm">
                            @error('formState') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
                @if ($formError !== '')
                    <p class="mt-3 text-xs text-red-600">{{ $formError }}</p>
                @endif
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                    <button type="button" wire:click="savePunch" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
