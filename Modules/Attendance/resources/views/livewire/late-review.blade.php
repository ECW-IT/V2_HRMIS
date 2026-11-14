<div class="mx-auto max-w-[1280px] space-y-4 px-6 py-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-lg font-semibold text-gray-800">Late review</h1>
        <label class="text-xs text-gray-600">Period
            <select wire:model.live="periodId" class="mt-1 block rounded border px-3 py-1 text-sm">
                @foreach($periods as $item)
                    <option value="{{ $item->id }}">{{ $item->year }}-{{ str_pad((string) $item->month, 2, '0', STR_PAD_LEFT) }}</option>
                @endforeach
            </select>
        </label>
    </div>

    @if($notice !== '')
        <p class="rounded bg-green-100 px-4 py-2 text-sm text-green-700">{{ $notice }}</p>
    @endif
    @if($error !== '')
        <p class="rounded bg-red-100 px-4 py-2 text-sm text-red-700">{{ $error }}</p>
    @endif

    @forelse($groups as $group)
        <div class="overflow-x-auto rounded bg-white shadow" wire:key="late-{{ $group['employment']->id }}">
            <div class="flex items-center justify-between border-b px-5 py-3">
                <h2 class="text-sm font-semibold text-gray-700">{{ $group['employee_no'] }} · {{ $group['name'] }}</h2>
                <p class="text-sm text-gray-700">Chargeable late minutes: {{ $group['chargeable'] }}</p>
            </div>
            <table class="w-full table-compact text-sm">
                <thead class="border-b bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Date</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Late minutes</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($group['rows'] as $row)
                        <tr>
                            <td class="px-3 py-1.5 text-xs text-gray-700">{{ $row->work_date->toDateString() }}</td>
                            <td class="px-3 py-1.5 text-right text-xs text-gray-700">{{ (int) $row->late_in_minutes + (int) $row->early_out_minutes }}</td>
                            <td class="px-3 py-1.5 text-xs">
                                @if($row->late_waived)
                                    <span class="text-gray-500">Waived</span>
                                @else
                                    @can('late.waive')
                                        <button type="button" wire:click="openWaive({{ $row->id }})" class="text-blue-700 hover:underline">Waive late</button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <p class="text-sm text-gray-500">No late minutes in this period.</p>
    @endforelse

    @if($activeId)
        <div class="rounded bg-white p-4 shadow">
            <h3 class="text-sm font-semibold text-gray-800">Waive late</h3>
            <label class="mt-3 block text-xs text-gray-600">Reason
                <input type="text" wire:model="reason" class="mt-1 w-full max-w-md rounded border px-3 py-2 text-sm" />
            </label>
            <button type="button" wire:click="saveWaive" class="mt-3 rounded bg-blue-600 px-3 py-1 text-xs text-white">Save</button>
        </div>
    @endif
</div>
