<div>
    <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Transfer to sister company</h3>

    @include('corehr::partials.action-feedback')

    <p class="mb-4 rounded bg-blue-50 px-3 py-2 text-xs text-blue-800">A new employee number will be issued in the target company.</p>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Target company <span class="text-red-500">*</span></label>
            <select wire:model.live="targetCompanyId" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('targetCompanyId') border-red-400 @enderror">
                <option value="">— Select —</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                @endforeach
            </select>
            @error('targetCompanyId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Last working day <span class="text-red-500">*</span></label>
            <input wire:model="lastWorkingDay" type="date"
                class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('lastWorkingDay') border-red-400 @enderror" />
            @error('lastWorkingDay')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">New department / section <span class="text-red-500">*</span></label>
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
        @if($employment->epf_liable)
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">New EPF no. <span class="text-red-500">*</span></label>
                <input wire:model="epfNo" type="text" maxlength="20"
                    class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('epfNo') border-red-400 @enderror" />
                @error('epfNo')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
        @endif
    </div>

    <div class="mt-5 flex justify-end gap-2">
        <button type="button" wire:click="$dispatch('close-action')" class="rounded border px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
            class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Transfer</button>
    </div>
</div>
