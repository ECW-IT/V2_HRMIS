@php
    $input = 'w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500';
    $steps = [1 => 'Who', 2 => 'Personal details', 3 => 'Employment', 4 => 'Bank and fingerprint', 5 => 'Review'];
@endphp

<div class="mx-auto max-w-4xl">
    <h2 class="mb-4 text-lg font-semibold text-gray-800">Hire employee</h2>

    <ol class="mb-6 flex flex-wrap gap-2 text-xs">
        @foreach($steps as $number => $label)
            <li class="rounded-full px-3 py-1 {{ $step === $number ? 'bg-blue-600 text-white' : ($step > $number ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500') }}">
                {{ $number }}. {{ $label }}
            </li>
        @endforeach
    </ol>

    <div class="rounded border bg-white p-5 shadow-sm">
        {{-- Step 1 --}}
        @if($step === 1)
            <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Who is joining?</h3>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Company <span class="text-red-500">*</span></label>
                    <select wire:model="companyId" class="{{ $input }} @error('companyId') border-red-400 @enderror">
                        <option value="">Select</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </select>
                    @error('companyId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">NIC</label>
                    <input wire:model="nic" type="text" maxlength="20" class="{{ $input }} @error('nic') border-red-400 @enderror" />
                    @error('nic')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Passport no. (if no NIC)</label>
                    <input wire:model="passportNo" type="text" maxlength="20" class="{{ $input }} @error('passportNo') border-red-400 @enderror" />
                    @error('passportNo')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>

            @if($alreadyEmployedMessage !== '')
                <div class="mt-4 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    {{ $alreadyEmployedMessage }}
                    @if($alreadyEmployedId)
                        <a href="{{ route('employees.show', $alreadyEmployedId) }}" class="font-medium text-blue-700 underline">Open profile</a>
                    @endif
                </div>
            @endif
        @endif

        {{-- Step 2 --}}
        @if($step === 2)
            <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Personal details</h3>

            @if($mode === 'REJOIN')
                <p class="mb-3 rounded bg-blue-50 px-3 py-2 text-xs text-blue-800">This person worked here before. They will be rejoined with a new employee number.</p>
            @elseif($existingPersonId)
                <p class="mb-3 rounded bg-blue-50 px-3 py-2 text-xs text-blue-800">This person is already in the system. Their details are filled in below.</p>
            @endif

            @if(count($pastEmployments) > 0)
                <div class="mb-4 overflow-x-auto rounded border">
                    <p class="border-b bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-600">Past employments</p>
                    <table class="min-w-full text-xs">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="px-3 py-1.5">Company</th>
                                <th class="px-3 py-1.5">Employee no.</th>
                                <th class="px-3 py-1.5">Hired</th>
                                <th class="px-3 py-1.5">Left</th>
                                <th class="px-3 py-1.5">Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pastEmployments as $past)
                                <tr class="border-t">
                                    <td class="px-3 py-1.5">{{ $past['company'] }}</td>
                                    <td class="px-3 py-1.5 font-mono">{{ $past['employee_no'] }}</td>
                                    <td class="px-3 py-1.5">{{ $past['hire_date'] }}</td>
                                    <td class="px-3 py-1.5">{{ $past['exit_date'] }}</td>
                                    <td class="px-3 py-1.5">{{ $past['exit_reason'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Title</label>
                    <input wire:model="title" type="text" maxlength="20" class="{{ $input }}" />
                    @error('title')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">First name <span class="text-red-500">*</span></label>
                    <input wire:model="firstName" type="text" maxlength="100" class="{{ $input }} @error('firstName') border-red-400 @enderror" />
                    @error('firstName')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Middle name</label>
                    <input wire:model="middleName" type="text" maxlength="100" class="{{ $input }}" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Last name <span class="text-red-500">*</span></label>
                    <input wire:model="lastName" type="text" maxlength="100" class="{{ $input }} @error('lastName') border-red-400 @enderror" />
                    @error('lastName')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Name with initials <span class="text-red-500">*</span></label>
                    <input wire:model="nameWithInitials" type="text" maxlength="150" class="{{ $input }} @error('nameWithInitials') border-red-400 @enderror" />
                    @error('nameWithInitials')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Calling name</label>
                    <input wire:model="callingName" type="text" maxlength="100" class="{{ $input }}" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Date of birth <span class="text-red-500">*</span></label>
                    <input wire:model="dateOfBirth" type="date" @readonly($identityLocked)
                        class="{{ $input }} {{ $identityLocked ? 'bg-gray-100' : '' }} @error('dateOfBirth') border-red-400 @enderror" />
                    @error('dateOfBirth')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Gender <span class="text-red-500">*</span></label>
                    <select wire:model="gender" @disabled($identityLocked) class="{{ $input }} {{ $identityLocked ? 'bg-gray-100' : '' }} @error('gender') border-red-400 @enderror">
                        <option value="">Select</option>
                        @foreach($genders as $option)
                            <option value="{{ $option->value }}">{{ ucfirst(strtolower($option->value)) }}</option>
                        @endforeach
                    </select>
                    @error('gender')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Marital status</label>
                    <select wire:model="maritalStatus" class="{{ $input }}">
                        <option value="">Select</option>
                        @foreach($maritalStatuses as $option)
                            <option value="{{ $option->value }}">{{ ucfirst(strtolower($option->value)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Nationality</label>
                    <input wire:model="nationality" type="text" maxlength="50" class="{{ $input }}" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Mobile <span class="text-red-500">*</span></label>
                    <input wire:model="mobile" type="text" maxlength="20" class="{{ $input }} @error('mobile') border-red-400 @enderror" />
                    @error('mobile')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Email</label>
                    <input wire:model="email" type="email" maxlength="150" class="{{ $input }} @error('email') border-red-400 @enderror" />
                    @error('email')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>

            <h4 class="mb-2 mt-6 text-xs font-semibold uppercase tracking-wide text-gray-500">Permanent address</h4>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-gray-600">Address line 1 <span class="text-red-500">*</span></label>
                    <input wire:model="addressLine1" type="text" maxlength="150" class="{{ $input }} @error('addressLine1') border-red-400 @enderror" />
                    @error('addressLine1')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Address line 2</label>
                    <input wire:model="addressLine2" type="text" maxlength="150" class="{{ $input }}" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">City <span class="text-red-500">*</span></label>
                    <input wire:model="city" type="text" maxlength="80" class="{{ $input }} @error('city') border-red-400 @enderror" />
                    @error('city')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">District</label>
                    <input wire:model="district" type="text" maxlength="50" class="{{ $input }}" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Postal code</label>
                    <input wire:model="postalCode" type="text" maxlength="10" class="{{ $input }}" />
                </div>
            </div>

            <h4 class="mb-2 mt-6 text-xs font-semibold uppercase tracking-wide text-gray-500">Emergency contact</h4>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Name</label>
                    <input wire:model="emergencyName" type="text" maxlength="150" class="{{ $input }} @error('emergencyName') border-red-400 @enderror" />
                    @error('emergencyName')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Relationship</label>
                    <input wire:model="emergencyRelationship" type="text" maxlength="50" class="{{ $input }}" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Phone</label>
                    <input wire:model="emergencyPhone" type="text" maxlength="20" class="{{ $input }}" />
                </div>
            </div>
        @endif

        {{-- Step 3 --}}
        @if($step === 3)
            <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Employment</h3>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Type <span class="text-red-500">*</span></label>
                    <select wire:model.live="type" class="{{ $input }} @error('type') border-red-400 @enderror">
                        <option value="">Select</option>
                        @foreach($types as $option)
                            <option value="{{ $option->value }}">{{ ucfirst(strtolower($option->value)) }}</option>
                        @endforeach
                    </select>
                    @error('type')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Hire date <span class="text-red-500">*</span></label>
                    <input wire:model.live="hireDate" type="date" class="{{ $input }} @error('hireDate') border-red-400 @enderror" />
                    @error('hireDate')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                @if($type === 'PROBATION')
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Confirmation due</label>
                        <input wire:model="confirmationDueDate" type="date" class="{{ $input }} @error('confirmationDueDate') border-red-400 @enderror" />
                        @error('confirmationDueDate')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                @endif
                @if($type !== 'INTERN')
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input wire:model.live="epfLiable" type="checkbox" class="rounded border" /> EPF liable
                        </label>
                    </div>
                    @if($epfLiable)
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">EPF no. <span class="text-red-500">*</span></label>
                            <input wire:model="epfNo" type="text" maxlength="20" class="{{ $input }} @error('epfNo') border-red-400 @enderror" />
                            @error('epfNo')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                    @endif
                @endif
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Department / section <span class="text-red-500">*</span></label>
                    <select wire:model="orgUnitId" class="{{ $input }} @error('orgUnitId') border-red-400 @enderror">
                        <option value="">Select</option>
                        @foreach($orgUnits as $unit)
                            <option value="{{ $unit['id'] }}">{{ $unit['label'] }}</option>
                        @endforeach
                    </select>
                    @error('orgUnitId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Job <span class="text-red-500">*</span></label>
                    <select wire:model="jobId" class="{{ $input }} @error('jobId') border-red-400 @enderror">
                        <option value="">Select</option>
                        @foreach($jobs as $job)
                            <option value="{{ $job->id }}">{{ $job->name }}</option>
                        @endforeach
                    </select>
                    @error('jobId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Grade <span class="text-red-500">*</span></label>
                    <select wire:model="gradeId" class="{{ $input }} @error('gradeId') border-red-400 @enderror">
                        <option value="">Select</option>
                        @foreach($grades as $grade)
                            <option value="{{ $grade->id }}">{{ $grade->name }}</option>
                        @endforeach
                    </select>
                    @error('gradeId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Location <span class="text-red-500">*</span></label>
                    <select wire:model="locationId" class="{{ $input }} @error('locationId') border-red-400 @enderror">
                        <option value="">Select</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                    @error('locationId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    @include('corehr::partials.manager-picker')
                </div>
            </div>
        @endif

        {{-- Step 4 --}}
        @if($step === 4)
            <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Bank and fingerprint</h3>

            <label class="mb-4 flex items-center gap-2 text-sm text-gray-700">
                <input wire:model.live="isCash" type="checkbox" class="rounded border" /> Paid in cash (no bank account)
            </label>

            @unless($isCash)
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Bank <span class="text-red-500">*</span></label>
                        <select wire:model.live="bankId" class="{{ $input }} @error('bankId') border-red-400 @enderror">
                            <option value="">Select</option>
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                            @endforeach
                        </select>
                        @error('bankId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Branch <span class="text-red-500">*</span></label>
                        <select wire:model="bankBranchId" class="{{ $input }} @error('bankBranchId') border-red-400 @enderror">
                            <option value="">Select</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('bankBranchId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Account number <span class="text-red-500">*</span></label>
                        <input wire:model="accountNo" type="text" maxlength="30" class="{{ $input }} @error('accountNo') border-red-400 @enderror" />
                        @error('accountNo')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Account holder name <span class="text-red-500">*</span></label>
                        <input wire:model="accountName" type="text" maxlength="150" class="{{ $input }} @error('accountName') border-red-400 @enderror" />
                        @error('accountName')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                </div>
            @endunless

            <h4 class="mb-2 mt-6 text-xs font-semibold uppercase tracking-wide text-gray-500">Fingerprint device (optional)</h4>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Device group</label>
                    <input wire:model="deviceGroup" type="text" maxlength="20" list="device-groups" class="{{ $input }} @error('deviceGroup') border-red-400 @enderror" />
                    <datalist id="device-groups">
                        @foreach($deviceGroups as $group)
                            <option value="{{ $group }}"></option>
                        @endforeach
                    </datalist>
                    @error('deviceGroup')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Device user ID</label>
                    <input wire:model="deviceUserId" type="number" min="1" class="{{ $input }} @error('deviceUserId') border-red-400 @enderror" />
                    @error('deviceUserId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>
        @endif

        {{-- Step 5 --}}
        @if($step === 5)
            <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Review</h3>

            @if($domainError !== '')
                <div class="mb-4 rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800">{{ $domainError }}</div>
            @endif

            <p class="mb-4 rounded bg-blue-50 px-3 py-2 text-xs text-blue-800">The employee number will be issued when you save.</p>

            <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm md:grid-cols-2">
                @foreach($review as $label => $value)
                    <div class="flex gap-2">
                        <dt class="w-40 shrink-0 text-gray-500">{{ $label }}</dt>
                        <dd class="text-gray-800">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        <div class="mt-6 flex justify-between border-t pt-4">
            <div>
                @if($step > 1)
                    <button type="button" wire:click="back" class="rounded border px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Back</button>
                @endif
            </div>
            <div class="flex gap-2">
                <a href="{{ route('employees.index') }}" class="rounded border px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Cancel</a>
                @if($step < 5)
                    <button type="button" wire:click="next" wire:loading.attr="disabled" wire:target="next"
                        class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Next</button>
                @else
                    <button type="button" wire:click="hire" wire:loading.attr="disabled" wire:target="hire"
                        class="rounded bg-green-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">Hire</button>
                @endif
            </div>
        </div>
    </div>
</div>
