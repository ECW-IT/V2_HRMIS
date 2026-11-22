<div class="space-y-4">
    @include('corehr::partials.action-feedback')

    <div class="rounded bg-white p-5 shadow">
        <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Photo</h3>
        <div class="flex flex-wrap items-center gap-4">
            <div wire:key="person-photo-{{ $person->photo_path ?? 'none' }}-{{ $photo ? 'pending' : 'saved' }}">
                @if($photo)
                    <img src="{{ $photo->temporaryUrl() }}" alt="Selected photo preview"
                        class="h-20 w-20 rounded-full object-cover ring-1 ring-blue-300" />
                @elseif($person->photo_path)
                    <img src="{{ route('persons.photo', $person) }}?v={{ urlencode($person->photo_path) }}" alt="Employee photo"
                        class="h-20 w-20 rounded-full object-cover ring-1 ring-gray-200" />
                @else
                    <div class="flex h-20 w-20 items-center justify-center rounded-full bg-gray-100 text-sm text-gray-500">No photo</div>
                @endif
            </div>
            @if($canEdit)
                <div class="min-w-0 flex-1 space-y-2">
                    <input type="file" wire:model.live="photo" accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                        class="block w-full text-sm text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-blue-700" />
                    @error('photo')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                    @if($photo)
                        <p class="text-xs text-blue-700">Selected — click Upload photo</p>
                    @endif
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="uploadPhoto" wire:loading.attr="disabled" wire:target="uploadPhoto,photo"
                            class="rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50">Upload photo</button>
                        @if($person->photo_path)
                            <button type="button" wire:click="removePhoto" wire:confirm="Remove this photo?"
                                class="rounded border px-3 py-1.5 text-xs text-red-600 hover:bg-red-50">Remove</button>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="rounded bg-white p-5 shadow">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded border bg-gray-50 px-3 py-2 text-sm text-gray-700">
                <span class="mb-1 block text-xs text-gray-500">NIC</span>
                <span class="font-mono text-xs">{{ $person->nic ?: '—' }}</span>
            </div>
            <div class="rounded border bg-gray-50 px-3 py-2 text-sm text-gray-700">
                <span class="mb-1 block text-xs text-gray-500">Date of birth</span>
                {{ $person->date_of_birth->format('Y-m-d') }}
            </div>
            <div class="rounded border bg-gray-50 px-3 py-2 text-sm text-gray-700">
                <span class="mb-1 block text-xs text-gray-500">Gender</span>
                {{ ucfirst(strtolower($person->gender->value)) }}
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Full name <span class="text-red-500">*</span></label>
                <input wire:model.live.debounce.400ms="fullName" type="text" maxlength="150" @disabled(! $canEdit)
                    class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('fullName') border-red-400 @enderror" />
                @error('fullName')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Name with initials <span class="text-red-500">*</span></label>
                <input wire:model="nameWithInitials" type="text" maxlength="150" @disabled(! $canEdit)
                    class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('nameWithInitials') border-red-400 @enderror" />
                @error('nameWithInitials')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Calling name</label>
                <input wire:model="callingName" type="text" maxlength="100" @disabled(! $canEdit)
                    class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('callingName') border-red-400 @enderror" />
                @error('callingName')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Marital status</label>
                <select wire:model="maritalStatus" @disabled(! $canEdit) class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="">— Select —</option>
                    @foreach($maritalStatuses as $status)
                        <option value="{{ $status->value }}">{{ ucfirst(strtolower($status->value)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Nationality</label>
                <input wire:model="nationality" type="text" maxlength="50" @disabled(! $canEdit)
                    class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500" />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Region</label>
                <select wire:model="region" @disabled(! $canEdit) class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('region') border-red-400 @enderror">
                    <option value="">— Select —</option>
                    @foreach($regions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                @error('region')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Arrive work from</label>
                <select wire:model="arriveWorkFrom" @disabled(! $canEdit) class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('arriveWorkFrom') border-red-400 @enderror">
                    <option value="">— Select —</option>
                    @foreach($arriveWorkFromOptions as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
                @error('arriveWorkFrom')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Driving licence no.</label>
                <input wire:model="drivingLicenceNo" type="text" maxlength="50" @disabled(! $canEdit)
                    class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('drivingLicenceNo') border-red-400 @enderror" />
                @error('drivingLicenceNo')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Landline no.</label>
                <input wire:model="landlineNo" type="text" maxlength="20" @disabled(! $canEdit)
                    class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('landlineNo') border-red-400 @enderror" />
                @error('landlineNo')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Office no.</label>
                <input wire:model="officeNo" type="text" maxlength="20" @disabled(! $canEdit)
                    class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('officeNo') border-red-400 @enderror" />
                @error('officeNo')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Province</label>
                <select wire:model.live="province" @disabled(! $canEdit) class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="">— Select —</option>
                    @foreach($provinces as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Electorate district</label>
                <select wire:model.live="electorateDistrict" @disabled(! $canEdit) class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="">— Select —</option>
                    @foreach($electorateDistricts as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">GN division</label>
                <x-gn-division-picker
                    :suggestions="$gnDivisionSuggestions"
                    :value="$gnDivision"
                    :disabled="! $canEdit"
                />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Distance (km)</label>
                <input wire:model="distanceKm" type="number" min="0" step="0.1" @disabled(! $canEdit)
                    class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500" />
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-2">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 md:col-span-2">Permanent address</h4>
            <div>
                <input wire:model="addresses.PERMANENT.line1" type="text" maxlength="150" placeholder="Address line 1" @disabled(! $canEdit)
                    class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('addresses.PERMANENT.line1') border-red-400 @enderror" />
                @error('addresses.PERMANENT.line1')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <input wire:model="addresses.PERMANENT.city" type="text" maxlength="80" placeholder="City" @disabled(! $canEdit)
                    class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('addresses.PERMANENT.city') border-red-400 @enderror" />
                @error('addresses.PERMANENT.city')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="mt-6">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Contacts</h4>
                @if($canEdit && ! $showContactForm)
                    <button type="button" wire:click="openAddContact"
                        class="rounded border border-blue-600 px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50">Add contact</button>
                @endif
            </div>

            @if($person->contacts->isNotEmpty())
                <ul class="mb-4 divide-y text-sm">
                    @foreach($person->contacts as $contact)
                        <li class="flex items-center gap-3 py-1.5">
                            <span class="w-28 shrink-0 text-xs text-gray-500">{{ ucfirst(strtolower(str_replace('_', ' ', $contact->type->value))) }}</span>
                            <span class="min-w-0 flex-1 text-gray-700">{{ $contact->value }}</span>
                            @if($contact->is_primary)
                                <span class="rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">Primary</span>
                            @endif
                            @if($canEdit)
                                <span class="ml-auto flex shrink-0 items-center gap-1">
                                    <x-action-icon-button variant="edit" wire:click="openEditContact({{ $contact->id }})" />
                                    <x-action-icon-button variant="remove" wire:click="removeContact({{ $contact->id }})" wire:confirm="Remove this contact?" />
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mb-4 text-sm text-gray-500">No contacts recorded.</p>
            @endif

            @if($canEdit && $showContactForm)
                <div class="rounded border bg-gray-50 p-3">
                    <p class="mb-3 text-xs font-medium text-gray-600">{{ $editingContactId ? 'Edit contact' : 'Add contact' }}</p>
                    <div class="grid grid-cols-1 items-end gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Type</label>
                            <select wire:model="newContactType" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                                @foreach($contactTypes as $contactType)
                                    <option value="{{ $contactType->value }}">{{ ucfirst(strtolower(str_replace('_', ' ', $contactType->value))) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Value</label>
                            <input wire:model="newContactValue" type="text" maxlength="150" placeholder="Number or address"
                                class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('newContactValue') border-red-400 @enderror" />
                            @error('newContactValue')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <label class="flex items-center gap-2 pb-1.5 text-sm text-gray-700">
                            <input type="checkbox" wire:model="newContactPrimary" class="rounded border" /> Primary
                        </label>
                        <div class="flex flex-wrap gap-2 md:justify-end">
                            <button type="button" wire:click="closeContactForm" class="rounded border px-3 py-1.5 text-sm text-gray-700 hover:bg-white">Cancel</button>
                            <button type="button" wire:click="saveContact" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Save contact</button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if($canEdit)
        <div class="flex justify-end">
            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Save</button>
        </div>
    @endif

    <div class="overflow-x-auto rounded bg-white shadow">
        <div class="flex items-center justify-between border-b px-5 py-3">
            <h3 class="text-sm font-semibold text-gray-700">Emergency contacts</h3>
            @if($canEdit)
                <button type="button" wire:click="openAddEmergency" class="rounded border border-blue-600 px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50">Add emergency contact</button>
            @endif
        </div>
        <table class="w-full table-compact text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Name</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Relationship</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Phone</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Address</th>
                    @if($canEdit)<th class="px-3 py-2 text-right text-xs font-semibold text-gray-600"></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse($person->emergencyContacts as $contact)
                    <tr class="border-t">
                        <td class="px-3 py-2 text-gray-700">{{ $contact->name }}</td>
                        <td class="px-3 py-2 text-gray-700">{{ $contact->relationship }}</td>
                        <td class="px-3 py-2 text-gray-700">{{ $contact->phone }}</td>
                        <td class="px-3 py-2 text-gray-700">{{ $contact->address ?: '—' }}</td>
                        @if($canEdit)
                            <td class="whitespace-nowrap px-3 py-2 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <x-action-icon-button variant="edit" wire:click="openEditEmergency({{ $contact->id }})" />
                                    <x-action-icon-button variant="remove" wire:click="removeChild('emergency', {{ $contact->id }})" wire:confirm="Remove this emergency contact?" />
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canEdit ? 5 : 4 }}" class="px-3 py-4 text-center text-sm text-gray-500">None recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="overflow-x-auto rounded bg-white shadow">
        <div class="flex items-center justify-between border-b px-5 py-3">
            <h3 class="text-sm font-semibold text-gray-700">Dependents</h3>
            @if($canEdit)
                <button type="button" wire:click="openAddDependent" class="rounded border border-blue-600 px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50">Add dependent</button>
            @endif
        </div>
        <table class="w-full table-compact text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Name</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Relationship</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Date of birth</th>
                    @if($canEdit)<th class="px-3 py-2 text-right text-xs font-semibold text-gray-600"></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse($person->dependents as $dependent)
                    <tr class="border-t">
                        <td class="px-3 py-2 text-gray-700">{{ $dependent->name }}</td>
                        <td class="px-3 py-2 text-gray-700">{{ $dependent->relationship }}</td>
                        <td class="px-3 py-2 text-gray-700">{{ $dependent->date_of_birth?->format('Y-m-d') ?? '—' }}</td>
                        @if($canEdit)
                            <td class="whitespace-nowrap px-3 py-2 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <x-action-icon-button variant="edit" wire:click="openEditDependent({{ $dependent->id }})" />
                                    <x-action-icon-button variant="remove" wire:click="removeChild('dependent', {{ $dependent->id }})" wire:confirm="Remove this dependent?" />
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canEdit ? 4 : 3 }}" class="px-3 py-4 text-center text-sm text-gray-500">None recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="overflow-x-auto rounded bg-white shadow">
        <div class="flex items-center justify-between border-b px-5 py-3">
            <h3 class="text-sm font-semibold text-gray-700">Qualifications</h3>
            @if($canEdit)
                <button type="button" wire:click="openAddQualification" class="rounded border border-blue-600 px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50">Add qualification</button>
            @endif
        </div>
        <table class="w-full table-compact text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Title</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Type</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Institute</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Year</th>
                    @if($canEdit)<th class="px-3 py-2 text-right text-xs font-semibold text-gray-600"></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse($person->qualifications as $qualification)
                    <tr class="border-t">
                        <td class="px-3 py-2 text-gray-700">{{ $qualification->title }}</td>
                        <td class="px-3 py-2 text-gray-700">{{ $qualification->type }}</td>
                        <td class="px-3 py-2 text-gray-700">{{ $qualification->institute }}</td>
                        <td class="px-3 py-2 text-gray-700">{{ $qualification->year ?? '—' }}</td>
                        @if($canEdit)
                            <td class="whitespace-nowrap px-3 py-2 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <x-action-icon-button variant="edit" wire:click="openEditQualification({{ $qualification->id }})" />
                                    <x-action-icon-button variant="remove" wire:click="removeChild('qualification', {{ $qualification->id }})" wire:confirm="Remove this qualification?" />
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canEdit ? 5 : 4 }}" class="px-3 py-4 text-center text-sm text-gray-500">None recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="overflow-x-auto rounded bg-white shadow">
        <div class="flex items-center justify-between border-b px-5 py-3">
            <h3 class="text-sm font-semibold text-gray-700">Experience</h3>
            @if($canEdit)
                <button type="button" wire:click="openAddExperience" class="rounded border border-blue-600 px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50">Add experience</button>
            @endif
        </div>
        <table class="w-full table-compact text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Job title</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Employer</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">From</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">To</th>
                    @if($canEdit)<th class="px-3 py-2 text-right text-xs font-semibold text-gray-600"></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse($person->experience as $experience)
                    <tr class="border-t">
                        <td class="px-3 py-2 text-gray-700">{{ $experience->job_title }}</td>
                        <td class="px-3 py-2 text-gray-700">{{ $experience->employer }}</td>
                        <td class="px-3 py-2 text-gray-700">{{ $experience->from_date->format('Y-m-d') }}</td>
                        <td class="px-3 py-2 text-gray-700">{{ $experience->to_date?->format('Y-m-d') ?? 'present' }}</td>
                        @if($canEdit)
                            <td class="whitespace-nowrap px-3 py-2 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <x-action-icon-button variant="edit" wire:click="openEditExperience({{ $experience->id }})" />
                                    <x-action-icon-button variant="remove" wire:click="removeChild('experience', {{ $experience->id }})" wire:confirm="Remove this experience?" />
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canEdit ? 5 : 4 }}" class="px-3 py-4 text-center text-sm text-gray-500">None recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($showChildForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-4 shadow-xl">
                <h2 class="text-base font-semibold text-gray-800">
                    @if($childKind === 'emergency')
                        {{ $editingChildId ? 'Edit emergency contact' : 'Add emergency contact' }}
                    @elseif($childKind === 'dependent')
                        {{ $editingChildId ? 'Edit dependent' : 'Add dependent' }}
                    @elseif($childKind === 'qualification')
                        {{ $editingChildId ? 'Edit qualification' : 'Add qualification' }}
                    @else
                        {{ $editingChildId ? 'Edit experience' : 'Add experience' }}
                    @endif
                </h2>

                <div class="mt-3 space-y-3">
                    @if($childKind === 'emergency')
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Name <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="childName" maxlength="150" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('childName') border-red-400 @enderror" />
                            @error('childName')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Relationship <span class="text-red-500">*</span></label>
                            <select wire:model="childRelationship" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('childRelationship') border-red-400 @enderror">
                                <option value="">Select</option>
                                @foreach($relationships as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                            @error('childRelationship')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Phone <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="childPhone" maxlength="20" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('childPhone') border-red-400 @enderror" />
                            @error('childPhone')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Address</label>
                            <input type="text" wire:model="childAddress" maxlength="255" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('childAddress') border-red-400 @enderror" />
                            @error('childAddress')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                    @elseif($childKind === 'dependent')
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Name <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="childName" maxlength="150" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('childName') border-red-400 @enderror" />
                            @error('childName')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Relationship <span class="text-red-500">*</span></label>
                            <select wire:model="childRelationship" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('childRelationship') border-red-400 @enderror">
                                <option value="">Select</option>
                                @foreach($relationships as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                            @error('childRelationship')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Date of birth</label>
                            <input type="date" wire:model="childDateOfBirth" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('childDateOfBirth') border-red-400 @enderror" />
                            @error('childDateOfBirth')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                    @elseif($childKind === 'qualification')
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Title <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="childTitle" maxlength="150" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('childTitle') border-red-400 @enderror" />
                            @error('childTitle')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Type <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="childType" maxlength="50" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('childType') border-red-400 @enderror" />
                            @error('childType')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Institute <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="childInstitute" maxlength="150" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('childInstitute') border-red-400 @enderror" />
                            @error('childInstitute')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Year</label>
                            <input type="number" wire:model="childYear" min="1900" max="2100" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('childYear') border-red-400 @enderror" />
                            @error('childYear')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                    @elseif($childKind === 'experience')
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Employer <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="childEmployer" maxlength="150" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('childEmployer') border-red-400 @enderror" />
                            @error('childEmployer')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Job title <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="childJobTitle" maxlength="150" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('childJobTitle') border-red-400 @enderror" />
                            @error('childJobTitle')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">From <span class="text-red-500">*</span></label>
                            <input type="date" wire:model="childFromDate" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('childFromDate') border-red-400 @enderror" />
                            @error('childFromDate')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">To</label>
                            <input type="date" wire:model="childToDate" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('childToDate') border-red-400 @enderror" />
                            @error('childToDate')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                    @endif
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closeChildForm" class="rounded border px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="button" wire:click="saveChild" wire:loading.attr="disabled" wire:target="saveChild"
                        class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
