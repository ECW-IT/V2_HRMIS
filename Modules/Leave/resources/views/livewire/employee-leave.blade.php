<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-700">Leave</h3>
        <input type="month" wire:model.live="month" aria-label="Month" class="rounded border px-3 py-1 text-sm" />
    </div>

    @if($error !== '')
        <p class="rounded bg-red-100 px-4 py-2 text-sm text-red-700">{{ $error }}</p>
    @endif

    <div class="overflow-x-auto rounded bg-white shadow">
        <div class="border-b px-5 py-3 text-sm font-semibold text-gray-700">Requests</div>
        <table class="w-full table-compact text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Date</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Type</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($requests as $request)
                    <tr>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $request->date->toDateString() }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $request->leaveType?->name }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ ucfirst(strtolower($request->status->value)) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-3 py-6 text-center text-sm text-gray-500">No leave requests.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @foreach($lines as $line)
        <div class="overflow-x-auto rounded bg-white shadow">
            <div class="flex items-center justify-between border-b px-5 py-3">
                <h4 class="text-sm font-semibold text-gray-700">{{ $line['type'] }} · {{ $periodLabel }}</h4>
                <p class="text-sm text-gray-700">Balance {{ $line['balance'] }}</p>
            </div>
            <table class="w-full table-compact text-sm">
                <thead class="border-b bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Entry</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Days</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($line['entries'] as $entry)
                        <tr>
                            <td class="px-3 py-1.5 text-xs text-gray-700">{{ $entry['kind'] }}</td>
                            <td class="px-3 py-1.5 text-right text-xs text-gray-700">{{ $entry['days'] }}</td>
                            <td class="px-3 py-1.5 text-xs text-gray-700">{{ $entry['note'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    @can('leave.manage')
        <button type="button" wire:click="openAdjust" class="rounded border border-blue-600 px-3 py-1 text-xs text-blue-700">Adjust balance</button>
        @if($showAdjust)
            <div class="rounded bg-white p-4 shadow">
                <label class="block text-xs text-gray-600">Leave type
                    <select wire:model="leaveTypeId" class="mt-1 w-full rounded border px-3 py-2 text-sm">
                        <option value="">Choose</option>
                        @foreach($types as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="mt-3 block text-xs text-gray-600">Days
                    <input type="text" wire:model="days" class="mt-1 w-32 rounded border px-3 py-2 text-sm" />
                </label>
                <label class="mt-3 block text-xs text-gray-600">Note
                    <input type="text" wire:model="note" class="mt-1 w-full rounded border px-3 py-2 text-sm" />
                </label>
                <button type="button" wire:click="saveAdjust" class="mt-3 rounded bg-blue-600 px-3 py-1 text-xs text-white">Save</button>
            </div>
        @endif
    @endcan
</div>
