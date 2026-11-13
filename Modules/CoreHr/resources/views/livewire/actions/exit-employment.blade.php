<div>
    <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Exit employee</h3>

    @include('corehr::partials.action-feedback')

    <div class="grid grid-cols-1 gap-4">
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Last working day <span class="text-red-500">*</span></label>
            <input wire:model="lastWorkingDay" type="date"
                class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('lastWorkingDay') border-red-400 @enderror" />
            @error('lastWorkingDay')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Reason <span class="text-red-500">*</span></label>
            <select wire:model="reason" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('reason') border-red-400 @enderror">
                <option value="">— Select —</option>
                @foreach($reasons as $exitReason)
                    <option value="{{ $exitReason->value }}">{{ ucfirst(strtolower(str_replace('_', ' ', $exitReason->value))) }}</option>
                @endforeach
            </select>
            @error('reason')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Remarks</label>
            <textarea wire:model="remarks" rows="3" maxlength="1000"
                class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('remarks') border-red-400 @enderror"></textarea>
            @error('remarks')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="mt-5 flex justify-end gap-2">
        <button type="button" wire:click="$dispatch('close-action')" class="rounded border px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
            class="rounded bg-red-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">Exit employee</button>
    </div>
</div>
