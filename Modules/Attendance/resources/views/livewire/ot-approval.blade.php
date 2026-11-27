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
        <button type="button" wire:click="openPartSelected" class="rounded bg-blue-600 px-3 py-1 text-xs text-white">Approve part ticked</button>
        <button type="button" wire:click="openRefuseSelected" class="rounded bg-blue-600 px-3 py-1 text-xs text-white">Refuse ticked</button>
        <button type="button" wire:click="resetSelected" class="rounded bg-blue-600 px-3 py-1 text-xs text-white">Reset ticked</button>
    </div>

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="w-full table-compact text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-3 py-2">
                        <input type="checkbox" aria-label="Select all" wire:click="toggleAll" @checked($allSelected) @disabled($rows->isEmpty()) />
                    </th>
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
                        <td class="px-3 py-1.5 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5">
                                <x-action-icon-button variant="approve" wire:click="approveAll({{ $row->id }})" />
                                <x-action-icon-button variant="approve-part" wire:click="openPart({{ $row->id }})" />
                                <x-action-icon-button variant="refuse" wire:click="refuse({{ $row->id }})" />
                                <x-action-icon-button variant="reset" wire:click="resetDecision({{ $row->id }})" />
                            </div>
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
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4">
            <div class="popup w-full max-w-md rounded border bg-white p-4 shadow-lg">
                <h2 class="text-sm font-semibold text-gray-800">Approve part</h2>
                <p class="mt-1 text-xs text-gray-500">Minutes in steps of 30. A reason is required when approving less than the computed OT.</p>
                <label class="mt-3 block text-xs text-gray-600">Minutes
                    <input type="number" step="30" wire:model="partMinutes" class="mt-1 w-32 rounded border px-3 py-2 text-sm" />
                </label>
                <label class="mt-3 block text-xs text-gray-600">Reason
                    <input type="text" wire:model="partReason" class="mt-1 w-full rounded border px-3 py-2 text-sm" />
                </label>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closePart" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                    <button type="button" wire:click="approvePart" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
                </div>
            </div>
        </div>
    @endif

    @if($partBulkOpen)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="popup w-full max-w-md rounded border bg-white p-4 shadow-lg">
                <h2 class="text-sm font-semibold text-gray-800">Approve part</h2>
                <p class="mt-1 text-xs text-gray-500">Minutes in steps of 30. A reason is required when approving less than the computed OT.</p>
                <label class="mt-3 block text-xs text-gray-600">Minutes
                    <input type="number" step="30" wire:model="partMinutes" class="mt-1 w-32 rounded border px-3 py-2 text-sm" />
                </label>
                <label class="mt-3 block text-xs text-gray-600">Reason
                    <input type="text" wire:model="partReason" class="mt-1 w-full rounded border px-3 py-2 text-sm" />
                </label>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closePartSelected" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                    <button type="button" wire:click="approvePartSelected" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
                </div>
            </div>
        </div>
    @endif

    @if($refuseBulkOpen)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="popup w-full max-w-md rounded border bg-white p-4 shadow-lg">
                <h2 class="text-sm font-semibold text-gray-800">Refuse ticked</h2>
                <p class="mt-1 text-xs text-gray-500">A reason is required to refuse OT.</p>
                <label class="mt-3 block text-xs text-gray-600">Reason
                    <input type="text" wire:model="partReason" class="mt-1 w-full rounded border px-3 py-2 text-sm" />
                </label>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closeRefuseSelected" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                    <button type="button" wire:click="refuseSelected" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
