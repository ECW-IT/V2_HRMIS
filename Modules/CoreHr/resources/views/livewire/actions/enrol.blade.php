<div class="rounded border bg-gray-50 p-4">
    <h3 class="mb-3 text-sm font-semibold text-gray-700">Enrol in a device group</h3>

    @include('corehr::partials.action-feedback')

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Device group <span class="text-red-500">*</span></label>
            <input wire:model="deviceGroup" type="text" list="enrol-device-groups" maxlength="20"
                class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('deviceGroup') border-red-400 @enderror" />
            <datalist id="enrol-device-groups">
                @foreach($deviceGroups as $group)
                    <option value="{{ $group }}"></option>
                @endforeach
            </datalist>
            @error('deviceGroup')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Device user ID <span class="text-red-500">*</span></label>
            <input wire:model="deviceUserId" type="number" min="1"
                class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('deviceUserId') border-red-400 @enderror" />
            @error('deviceUserId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">From <span class="text-red-500">*</span></label>
            <input wire:model="from" type="date"
                class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('from') border-red-400 @enderror" />
            @error('from')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="mt-4 flex justify-end gap-2">
        <button type="button" wire:click="$dispatch('close-enrol')" class="rounded border px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
            class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Enrol</button>
    </div>
</div>
