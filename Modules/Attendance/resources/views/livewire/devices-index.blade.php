<div class="mx-auto max-w-6xl px-6 py-8">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Devices</h1>
            <p class="text-sm text-slate-500">Fingerprint terminals that send punches.</p>
        </div>
        <button type="button" wire:click="openRegister" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">
            Register device
        </button>
    </div>

    @if ($newToken)
        <div class="mb-4 rounded border border-amber-300 bg-amber-50 p-4">
            <p class="text-sm font-semibold text-amber-900">Device token</p>
            <p class="mt-1 text-sm text-amber-800">This token will not be shown again.</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <code class="rounded bg-white px-2 py-1 text-sm">{{ $newToken }}</code>
                <button type="button" x-data x-on:click="navigator.clipboard.writeText(@js($newToken))" class="rounded border px-2 py-1 text-sm">Copy</button>
                <button type="button" wire:click="dismissToken" class="rounded border px-2 py-1 text-sm">Close</button>
            </div>
        </div>
    @endif

    <div class="overflow-hidden rounded border bg-white">
        <table class="table-compact min-w-full text-left">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Serial</th>
                    <th class="px-3 py-2">Location</th>
                    <th class="px-3 py-2">Group</th>
                    <th class="px-3 py-2">Active</th>
                    <th class="px-3 py-2">Last seen</th>
                    <th class="px-3 py-2">Punches today</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($devices as $device)
                    @php
                        $stale = false;
                        $hour = (int) now(config('app.timezone'))->format('G');
                        if ($hour >= 6 && $hour < 20) {
                            $stale = $device->last_seen_at === null
                                || $device->last_seen_at->lt(now(config('app.timezone'))->subHours(2));
                        }
                    @endphp
                    <tr class="border-t">
                        <td class="px-3 py-2 font-medium">{{ $device->name }}</td>
                        <td class="px-3 py-2">{{ $device->serial_no }}</td>
                        <td class="px-3 py-2">{{ $device->location?->name }}</td>
                        <td class="px-3 py-2">{{ $device->device_group }}</td>
                        <td class="px-3 py-2">{{ $device->is_active ? 'Yes' : 'No' }}</td>
                        <td @class(['px-3 py-2', 'text-red-600 font-medium' => $stale])>
                            {{ $device->last_seen_at?->format('Y-m-d H:i') ?? '—' }}
                        </td>
                        <td class="px-3 py-2">{{ $device->punches_today }}</td>
                        <td class="px-3 py-2 text-right space-x-2 whitespace-nowrap">
                            <button type="button" wire:click="rotateToken({{ $device->id }})" class="text-[var(--color-erp-accent)] hover:underline">Rotate token</button>
                            <button type="button" wire:click="toggleActive({{ $device->id }})" class="text-slate-600 hover:underline">
                                {{ $device->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="8" class="px-3 py-4 text-sm text-slate-500">No devices registered.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="popup w-full max-w-md rounded border bg-white p-4 shadow-lg">
                <h2 class="mb-3 text-sm font-semibold">Register device</h2>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">Serial</label>
                        <input type="text" wire:model="serial_no" class="w-full rounded border px-3 py-1.5 text-sm">
                        @error('serial_no') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">Name</label>
                        <input type="text" wire:model="name" class="w-full rounded border px-3 py-1.5 text-sm">
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">Location</label>
                        <select wire:model="location_id" class="w-full rounded border px-3 py-1.5 text-sm">
                            <option value="">Select</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                        @error('location_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">Device group</label>
                        <input type="text" wire:model="device_group" class="w-full rounded border px-3 py-1.5 text-sm">
                        @error('device_group') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">IP address</label>
                        <input type="text" wire:model="ip_address" class="w-full rounded border px-3 py-1.5 text-sm">
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                    <button type="button" wire:click="register" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Register</button>
                </div>
            </div>
        </div>
    @endif
</div>
