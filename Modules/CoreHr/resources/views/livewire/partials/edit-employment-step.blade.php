@php
    $input = 'w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500';
@endphp

<div class="space-y-4">
    <p class="rounded bg-blue-50 px-3 py-2 text-xs text-blue-800">
        Employee number, EPF number and hire date cannot be edited here.
    </p>

    <div class="rounded border bg-white p-4">
        <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Employment</h3>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded border bg-gray-50 px-3 py-2 text-sm text-gray-700">
                <span class="mb-1 block text-xs text-gray-500">Employee no.</span>
                <span class="font-mono text-xs">{{ $employment->employee_no }}</span>
            </div>
            <div class="rounded border bg-gray-50 px-3 py-2 text-sm text-gray-700">
                <span class="mb-1 block text-xs text-gray-500">EPF no.</span>
                <span class="font-mono text-xs">{{ $employment->epf_liable ? ($employment->epf_no ?: '—') : 'Not liable' }}</span>
            </div>
            <div class="rounded border bg-gray-50 px-3 py-2 text-sm text-gray-700">
                <span class="mb-1 block text-xs text-gray-500">Hire date</span>
                {{ $employment->hire_date->format('Y-m-d') }}
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Type</label>
                <select wire:model="type" class="{{ $input }} @error('type') border-red-400 @enderror">
                    @foreach ($types as $employmentType)
                        <option value="{{ $employmentType->value }}">{{ ucfirst(strtolower($employmentType->value)) }}</option>
                    @endforeach
                </select>
                @error('type')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Employee category</label>
                <select wire:model.live="employeeCategoryId" class="{{ $input }} @error('employeeCategoryId') border-red-400 @enderror">
                    <option value="">None</option>
                    @foreach ($employeeCategories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('employeeCategoryId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                @if ($categoryTimeStatus !== '')
                    <p class="mt-1 text-xs text-gray-600">{{ $categoryTimeStatus }}</p>
                @endif
            </div>
            <div class="flex items-end">
                <button type="button" wire:click="saveClassification" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                    Save classification
                </button>
            </div>
        </div>
        @if ($classificationMessage !== '')
            <p class="mt-2 text-xs text-emerald-600">{{ $classificationMessage }}</p>
        @endif
    </div>

    <div class="rounded border bg-white p-4">
        <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Assignment</h3>
        @if ($message !== '')
            <p class="mb-3 text-xs text-emerald-600">{{ $message }}</p>
        @endif
        @if ($errorMessage !== '')
            <p class="mb-3 text-xs text-red-600">{{ $errorMessage }}</p>
        @endif
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Effective from <span class="text-red-500">*</span></label>
                <input type="date" wire:model="effectiveFrom" class="{{ $input }} @error('effectiveFrom') border-red-400 @enderror">
                @error('effectiveFrom')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Department / section <span class="text-red-500">*</span></label>
                <select wire:model="orgUnitId" class="{{ $input }} @error('orgUnitId') border-red-400 @enderror">
                    <option value="">— Select —</option>
                    <x-org-unit-options :units="$orgUnits" />
                </select>
                @error('orgUnitId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Designation <span class="text-red-500">*</span></label>
                <x-searchable-select
                    model="designationId"
                    :value="$designationId"
                    :options="$designations"
                    empty-label="— Select —"
                    aria-label="Designation"
                    search-placeholder="Search designation"
                    :input-class="$input.($errors->has('designationId') ? ' border-red-400' : '')"
                />
                @error('designationId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Location <span class="text-red-500">*</span></label>
                <select wire:model="locationId" class="{{ $input }} @error('locationId') border-red-400 @enderror">
                    <option value="">— Select —</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
                @error('locationId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-xs font-medium text-gray-600">Reason <span class="text-red-500">*</span></label>
                <input type="text" wire:model="reason" maxlength="255" class="{{ $input }} @error('reason') border-red-400 @enderror">
                @error('reason')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div class="flex items-end">
                <button type="button" wire:click="saveAssignment" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                    Save assignment
                </button>
            </div>
        </div>
    </div>

    <div class="rounded border bg-white p-4">
        <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">OT mode</h3>
        @if ($otModeMessage !== '')
            <p class="mb-3 text-xs text-emerald-600">{{ $otModeMessage }}</p>
        @endif
        @if ($otModeError !== '')
            <p class="mb-3 text-xs text-red-600">{{ $otModeError }}</p>
        @endif
        @if ($otProfile)
            <p class="mb-3 text-xs text-gray-600">In force today: {{ $otProfile->ot_mode?->value ?? 'Default' }}</p>
        @else
            <p class="mb-3 text-xs text-gray-600">No time profile is in force.</p>
        @endif
        @if ($categoryTimeStatus !== '')
            <p class="mb-3 text-xs text-gray-600">{{ $categoryTimeStatus }}</p>
        @endif
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Effective from <span class="text-red-500">*</span></label>
                <input type="date" wire:model="otEffectiveFrom" class="{{ $input }} @error('otEffectiveFrom') border-red-400 @enderror">
                @error('otEffectiveFrom')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">OT mode <span class="text-red-500">*</span></label>
                <select wire:model="otMode" class="{{ $input }} @error('otMode') border-red-400 @enderror">
                    <option value="DEFAULT">Default</option>
                    @foreach ($otModes as $mode)
                        <option value="{{ $mode->value }}">{{ $mode->value }}</option>
                    @endforeach
                </select>
                @error('otMode')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div class="flex items-end">
                <button type="button" wire:click="saveOtMode" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                    Save OT mode
                </button>
            </div>
        </div>
    </div>

    <div class="rounded border bg-white p-4">
        <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Late deduction</h3>
        @if ($lateDeductionMessage !== '')
            <p class="mb-3 text-xs text-emerald-600">{{ $lateDeductionMessage }}</p>
        @endif
        @if ($lateDeductionError !== '')
            <p class="mb-3 text-xs text-red-600">{{ $lateDeductionError }}</p>
        @endif
        @if ($otProfile)
            <p class="mb-3 text-xs text-gray-600">In force today: {{ $otProfile->late_deduction === null ? 'Default' : ($otProfile->late_deduction ? 'Yes' : 'No') }}</p>
        @else
            <p class="mb-3 text-xs text-gray-600">No time profile is in force.</p>
        @endif
        @if ($categoryTimeStatus !== '')
            <p class="mb-3 text-xs text-gray-600">{{ $categoryTimeStatus }}</p>
        @endif
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Effective from <span class="text-red-500">*</span></label>
                <input type="date" wire:model="lateEffectiveFrom" class="{{ $input }} @error('lateEffectiveFrom') border-red-400 @enderror">
                @error('lateEffectiveFrom')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Late deduction <span class="text-red-500">*</span></label>
                <select wire:model="lateDeduction" class="{{ $input }} @error('lateDeduction') border-red-400 @enderror">
                    <option value="DEFAULT">Default</option>
                    <option value="YES">Yes</option>
                    <option value="NO">No</option>
                </select>
                @error('lateDeduction')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div class="flex items-end">
                <button type="button" wire:click="saveLateDeduction" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                    Save late deduction
                </button>
            </div>
        </div>
    </div>

    <div class="rounded border bg-white p-4">
        <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Break available</h3>
        @if ($breakAvailableMessage !== '')
            <p class="mb-3 text-xs text-emerald-600">{{ $breakAvailableMessage }}</p>
        @endif
        @if ($breakAvailableError !== '')
            <p class="mb-3 text-xs text-red-600">{{ $breakAvailableError }}</p>
        @endif
        @if ($otProfile)
            <p class="mb-3 text-xs text-gray-600">In force today: {{ $otProfile->break_available === null ? 'Default' : ($otProfile->break_available ? 'Yes' : 'No') }}</p>
        @else
            <p class="mb-3 text-xs text-gray-600">No time profile is in force.</p>
        @endif
        @if ($categoryTimeStatus !== '')
            <p class="mb-3 text-xs text-gray-600">{{ $categoryTimeStatus }}</p>
        @endif
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Effective from <span class="text-red-500">*</span></label>
                <input type="date" wire:model="breakEffectiveFrom" class="{{ $input }} @error('breakEffectiveFrom') border-red-400 @enderror">
                @error('breakEffectiveFrom')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Break available <span class="text-red-500">*</span></label>
                <select wire:model="breakAvailable" class="{{ $input }} @error('breakAvailable') border-red-400 @enderror">
                    <option value="DEFAULT">Default</option>
                    <option value="YES">Yes</option>
                    <option value="NO">No</option>
                </select>
                @error('breakAvailable')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div class="flex items-end">
                <button type="button" wire:click="saveBreakAvailable" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                    Save break available
                </button>
            </div>
        </div>
    </div>

    <div class="rounded border bg-white p-4">
        <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Office / shop category</h3>
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[14rem] flex-1">
                <label class="mb-1 block text-xs font-medium text-gray-600">Category</label>
                <select wire:model="staffWorkCategoryId" class="{{ $input }}">
                    <option value="">None</option>
                    @foreach ($staffWorkCategories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }} ({{ $category->code }})</option>
                    @endforeach
                </select>
            </div>
            <button type="button" wire:click="saveStaffWorkCategory" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                Save category
            </button>
        </div>
        @if ($staffWorkMessage !== '')
            <p class="mt-2 text-xs text-emerald-600">{{ $staffWorkMessage }}</p>
        @endif
    </div>

    <div class="rounded border bg-white p-4">
        <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Module flags</h3>
        <div class="flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-1.5 text-sm">
                <input type="checkbox" wire:model.live="hrmisActive" class="rounded border" @disabled($employment->isResigned()) /> HRMIS active
            </label>
            <label class="flex items-center gap-1.5 text-sm">
                <input type="checkbox" wire:model="attendanceActive" class="rounded border" @disabled($employment->isResigned()) /> Attendance active
            </label>
            <label class="flex items-center gap-1.5 text-sm">
                <input type="checkbox" wire:model="payrollActive" class="rounded border" /> Payroll active
            </label>
            <button type="button" wire:click="saveModuleFlags" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                Save flags
            </button>
        </div>
        @if ($moduleFlagsMessage !== '')
            <p class="mt-2 text-xs text-emerald-600">{{ $moduleFlagsMessage }}</p>
        @endif
    </div>
</div>
