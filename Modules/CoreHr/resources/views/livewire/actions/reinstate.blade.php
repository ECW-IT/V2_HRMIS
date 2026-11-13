<div>
    <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Reinstate employee</h3>

    @include('corehr::partials.action-feedback')

    <div>
        <label class="mb-1 block text-xs font-medium text-gray-600">Reinstated on <span class="text-red-500">*</span></label>
        <input wire:model="on" type="date"
            class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('on') border-red-400 @enderror" />
        @error('on')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
    </div>

    <div class="mt-5 flex justify-end gap-2">
        <button type="button" wire:click="$dispatch('close-action')" class="rounded border px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
            class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Reinstate</button>
    </div>
</div>
