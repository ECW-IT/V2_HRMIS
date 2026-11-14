<div class="mx-auto max-w-[1280px] space-y-4 px-6 py-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-lg font-semibold text-gray-800">Daily board</h1>
        <button type="button" wire:click="export" class="rounded border border-blue-600 px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50">Export</button>
    </div>

    <div class="flex flex-wrap gap-3">
        <label class="text-xs text-gray-600">Date
            <input type="date" wire:model.live="date" class="mt-1 block rounded border px-3 py-1 text-sm" />
        </label>
        <label class="text-xs text-gray-600">Org unit
            <select wire:model.live="orgUnitId" class="mt-1 block rounded border px-3 py-1 text-sm">
                <option value="">All</option>
                @foreach($units as $unit)
                    <option value="{{ $unit['id'] }}">{{ $unit['label'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs text-gray-600">Shift
            <select wire:model.live="shiftId" class="mt-1 block rounded border px-3 py-1 text-sm">
                <option value="">All</option>
                @foreach($shifts as $shift)
                    <option value="{{ $shift->id }}">{{ $shift->code }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs text-gray-600">Status
            <select wire:model.live="status" class="mt-1 block rounded border px-3 py-1 text-sm">
                <option value="">All</option>
                @foreach($statuses as $item)
                    <option value="{{ $item }}">{{ $item }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach($cards as $label => $count)
            <div class="rounded bg-white px-4 py-3 shadow">
                <div class="text-xs text-gray-500">{{ $label }}</div>
                <div class="text-xl font-semibold text-gray-800">{{ $count }}</div>
            </div>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="w-full table-compact text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Employee no.</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Name</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Org unit</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Shift</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Status</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">First in</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Last out</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Late minutes</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($rows as $row)
                    <tr class="cursor-pointer hover:bg-gray-50" wire:key="board-{{ $row['id'] }}" onclick="window.location='{{ route('attendance.timecards.show', $row['id']) }}?month={{ $row['month'] }}'">
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $row['employee_no'] }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $row['name'] }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $row['org_unit'] }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $row['shift'] }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $row['status'] }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $row['first_in'] }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $row['last_out'] }}</td>
                        <td class="px-3 py-1.5 text-right text-xs text-gray-700">{{ $row['late'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-6 text-center text-sm text-gray-500">No employees for this date.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
