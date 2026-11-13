<div>
    <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Change assignment</h3>

    @include('corehr::partials.action-feedback')

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Effective from <span class="text-red-500">*</span></label>
            <input wire:model="effectiveFrom" type="date"
                class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('effectiveFrom') border-red-400 @enderror" />
            @error('effectiveFrom')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Department / section <span class="text-red-500">*</span></label>
            <select wire:model="orgUnitId" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('orgUnitId') border-red-400 @enderror">
                <option value="">— Select —</option>
                @foreach($orgUnits as $unit)
                    <option value="{{ $unit['id'] }}">{{ $unit['label'] }}</option>
                @endforeach
            </select>
            @error('orgUnitId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Job <span class="text-red-500">*</span></label>
            <select wire:model="jobId" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('jobId') border-red-400 @enderror">
                <option value="">— Select —</option>
                @foreach($jobs as $job)
                    <option value="{{ $job->id }}">{{ $job->name }}</option>
                @endforeach
            </select>
            @error('jobId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Grade <span class="text-red-500">*</span></label>
            <select wire:model="gradeId" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('gradeId') border-red-400 @enderror">
                <option value="">— Select —</option>
                @foreach($grades as $grade)
                    <option value="{{ $grade->id }}">{{ $grade->name }}</option>
                @endforeach
            </select>
            @error('gradeId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Location <span class="text-red-500">*</span></label>
            <select wire:model="locationId" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('locationId') border-red-400 @enderror">
                <option value="">— Select —</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                @endforeach
            </select>
            @error('locationId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        @include('corehr::partials.manager-picker')
        <div class="md:col-span-2">
            <label class="mb-1 block text-xs font-medium text-gray-600">Reason <span class="text-red-500">*</span></label>
            <input wire:model="reason" type="text" maxlength="255"
                class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('reason') border-red-400 @enderror" />
            @error('reason')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="mt-5 flex justify-end gap-2">
        <button type="button" wire:click="$dispatch('close-action')" class="rounded border px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
            class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Save</button>
    </div>
</div>
