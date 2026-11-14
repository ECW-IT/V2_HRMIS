<div class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-black/40 p-6">
    <div class="popup mt-10 w-full max-w-md bg-white p-5 shadow-xl">
        <h3 class="text-sm font-semibold text-gray-800">Override</h3>
        <label class="mt-3 block text-xs text-gray-600">Field
            <select wire:model="overrideField" class="mt-1 w-full rounded border px-3 py-2 text-sm">
                <option value="NOPAY_DAYS">No-pay days</option>
                <option value="LATE_MINUTES_CHARGEABLE">Chargeable late minutes</option>
            </select>
        </label>
        <label class="mt-3 block text-xs text-gray-600">Value
            <input type="text" wire:model="overrideValue" class="mt-1 w-full rounded border px-3 py-2 text-sm" />
        </label>
        <label class="mt-3 block text-xs text-gray-600">Reason
            <input type="text" wire:model="overrideReason" class="mt-1 w-full rounded border px-3 py-2 text-sm" />
        </label>
        @if($error !== '')
            <p class="mt-2 text-xs text-red-600">{{ $error }}</p>
        @endif
        <div class="mt-4 flex justify-end gap-2">
            <button type="button" wire:click="$set('showOverride', false)" class="rounded border px-3 py-1 text-xs">Cancel</button>
            <button type="button" wire:click="saveOverride" class="rounded bg-blue-600 px-3 py-1 text-xs text-white">Save</button>
        </div>
    </div>
</div>
