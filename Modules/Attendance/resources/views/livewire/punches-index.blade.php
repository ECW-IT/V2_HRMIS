<div class="mx-auto max-w-6xl px-6 py-8">
    <div class="mb-4">
        <h1 class="text-lg font-semibold text-slate-800">Punches</h1>
        <p class="text-sm text-slate-500">Fingerprint punches stored from the terminals.</p>
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
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="7" class="px-3 py-4 text-sm text-slate-500">No punches for this filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $punches->links() }}
    </div>
</div>
