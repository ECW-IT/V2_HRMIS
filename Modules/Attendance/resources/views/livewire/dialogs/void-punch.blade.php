<div class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-black/40 p-6">
    <div class="popup mt-10 w-full max-w-md bg-white p-5 shadow-xl">
        <h3 class="text-sm font-semibold text-gray-800">Void punch</h3>
        <label class="mt-3 block text-xs text-gray-600">Reason
            <input type="text" wire:model="reason" class="mt-1 w-full rounded border px-3 py-2 text-sm" />
        </label>
        @if($error !== '')
            <p class="mt-2 text-xs text-red-600">{{ $error }}</p>
        @endif
        <div class="mt-4 flex justify-end gap-2">
            <button type="button" wire:click="$set('showVoid', false)" class="rounded border px-3 py-1 text-xs">Cancel</button>
            <button type="button" wire:click="saveVoid" class="rounded bg-blue-600 px-3 py-1 text-xs text-white">Save</button>
        </div>
    </div>
</div>
