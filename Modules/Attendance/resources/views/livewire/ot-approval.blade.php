<div class="mx-auto max-w-[1280px] space-y-4 px-6 py-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-lg font-semibold text-gray-800">OT approval</h1>
        <div class="flex flex-wrap gap-3">
            <label class="text-xs text-gray-600">Period
                <select wire:model.live="periodId" class="mt-1 block rounded border px-3 py-1 text-sm">
                    @foreach($periods as $item)
                        <option value="{{ $item->id }}">{{ $item->year }}-{{ str_pad((string) $item->month, 2, '0', STR_PAD_LEFT) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs text-gray-600">State
                <select wire:model.live="state" class="mt-1 block rounded border px-3 py-1 text-sm">
                    <option value="undecided">Undecided</option>
                    <option value="approved">Approved</option>
                    <option value="refused">Refused</option>
                    <option value="all">All</option>
                </select>
            </label>
        </div>
    </div>

    <p class="text-sm text-gray-700">OT hours <strong>{{ $otHours }}</strong></p>
    @if($notice !== '')
        <p class="rounded bg-green-100 px-4 py-2 text-sm text-green-700">{{ $notice }}</p>
    @endif
    @if($error !== '')
        <p class="rounded bg-red-100 px-4 py-2 text-sm text-red-700">{{ $error }}</p>
    @endif

    <div class="flex flex-wrap items-end gap-2">
        <button type="button" wire:click="approveSelected" class="rounded bg-blue-600 px-3 py-1 text-xs text-white">Approve all ticked</button>
    </div>

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="w-full table-compact text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-3 py-2"></th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Employee no.</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Name</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Date</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Computed minutes</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Approved minutes</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($rows as $row)
                    <tr class="hover:bg-gray-50" wire:key="ot-{{ $row->id }}">
                        <td class="px-3 py-1.5"><input type="checkbox" wire:model="selected" value="{{ $row->id }}" /></td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $row->employment?->employee_no }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $row->employment?->person?->name_with_initials }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $row->work_date->toDateString() }}</td>
                        <td class="px-3 py-1.5 text-right text-xs text-gray-700">{{ (int) $row->ot_morning_minutes + (int) $row->ot_evening_minutes }}</td>
                        <td class="px-3 py-1.5 text-right text-xs text-gray-700">{{ $row->ot_approved_minutes === null ? '' : $row->ot_approved_minutes }}</td>
                        <td class="px-3 py-1.5 text-xs">
                            <button type="button" wire:click="approveAll({{ $row->id }})" class="mr-2 text-blue-700 hover:underline">Approve all</button>
                            <button type="button" wire:click="openPart({{ $row->id }})" class="mr-2 text-blue-700 hover:underline">Approve part</button>
                            <button type="button" wire:click="refuse({{ $row->id }})" class="mr-2 text-blue-700 hover:underline">Refuse</button>
                            <button type="button" wire:click="resetDecision({{ $row->id }})" class="text-blue-700 hover:underline">Reset</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center text-sm text-gray-500">No overtime in this list.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($activeId)
        <div class="rounded bg-white p-4 shadow">
            <h3 class="text-sm font-semibold text-gray-800">Approve part</h3>
            <p class="mt-1 text-xs text-gray-500">Minutes in steps of 30. A reason is required when approving less than the computed OT.</p>
            <label class="mt-3 block text-xs text-gray-600">Minutes
                <input type="number" step="30" wire:model="partMinutes" class="mt-1 w-32 rounded border px-3 py-2 text-sm" />
            </label>
            <label class="mt-3 block text-xs text-gray-600">Reason
                <input type="text" wire:model="partReason" class="mt-1 w-full max-w-md rounded border px-3 py-2 text-sm" />
            </label>
            <button type="button" wire:click="approvePart" class="mt-3 rounded bg-blue-600 px-3 py-1 text-xs text-white">Save</button>
        </div>
    @endif
</div>
