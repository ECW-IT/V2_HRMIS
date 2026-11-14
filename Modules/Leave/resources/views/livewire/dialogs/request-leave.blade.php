<div class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-black/40 p-6">
    <div class="popup mt-10 w-full max-w-lg bg-white p-5 shadow-xl">
        <h3 class="text-sm font-semibold text-gray-800">New leave</h3>
        <label class="mt-3 block text-xs text-gray-600">Employee
            <select wire:model.live="employmentId" class="mt-1 w-full rounded border px-3 py-2 text-sm">
                <option value="">Choose</option>
                @foreach($employments as $employment)
                    <option value="{{ $employment->id }}">{{ $employment->employee_no }} · {{ $employment->person?->name_with_initials }}</option>
                @endforeach
            </select>
        </label>
        <label class="mt-3 block text-xs text-gray-600">Leave type
            <select wire:model.live="leaveTypeId" class="mt-1 w-full rounded border px-3 py-2 text-sm">
                <option value="">Choose</option>
                @foreach($types as $type)
                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                @endforeach
            </select>
        </label>
        <div class="mt-3 flex gap-3">
            <label class="text-xs text-gray-600">From
                <input type="date" wire:model.live="from" class="mt-1 block rounded border px-3 py-1 text-sm" />
            </label>
            <label class="text-xs text-gray-600">To
                <input type="date" wire:model.live="to" class="mt-1 block rounded border px-3 py-1 text-sm" />
            </label>
        </div>
        @if($dates !== [])
            <div class="mt-3 max-h-40 space-y-1 overflow-y-auto text-xs text-gray-700">
                @foreach($dates as $date => $ticked)
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="dates.{{ $date }}" />
                        {{ $date }}
                    </label>
                @endforeach
            </div>
        @endif
        @foreach($balances as $balance)
            <p class="mt-2 text-xs text-gray-700">{{ $balance['label'] }} available: {{ $balance['available'] }}</p>
        @endforeach
        <label class="mt-3 block text-xs text-gray-600">Reason
            <input type="text" wire:model="reason" class="mt-1 w-full rounded border px-3 py-2 text-sm" />
        </label>
        @can('leave.manage')
            <label class="mt-3 flex items-center gap-2 text-xs text-gray-700">
                <input type="checkbox" wire:model="preApproved" />
                Pre-approved
            </label>
        @endcan
        <div class="mt-4 flex justify-end gap-2">
            <button type="button" wire:click="$set('showForm', false)" class="rounded border px-3 py-1 text-xs">Cancel</button>
            <button type="button" wire:click="save" class="rounded bg-blue-600 px-3 py-1 text-xs text-white">Save</button>
        </div>
    </div>
</div>
