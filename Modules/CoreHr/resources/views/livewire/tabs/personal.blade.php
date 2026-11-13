<div class="space-y-4">
    @include('corehr::partials.action-feedback')

    <div class="rounded bg-white p-5 shadow">
        <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Names</h3>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Title</label>
                <input wire:model="title" type="text" maxlength="20" @disabled(! $canEdit)
                    class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('title') border-red-400 @enderror" />
                @error('title')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">First name <span class="text-red-500">*</span></label>
                <input wire:model="firstName" type="text" maxlength="100" @disabled(! $canEdit)
                    class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('firstName') border-red-400 @enderror" />
                @error('firstName')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Middle name</label>
                <input wire:model="middleName" type="text" maxlength="100" @disabled(! $canEdit)
                    class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('middleName') border-red-400 @enderror" />
                @error('middleName')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Last name <span class="text-red-500">*</span></label>
                <input wire:model="lastName" type="text" maxlength="100" @disabled(! $canEdit)
                    class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('lastName') border-red-400 @enderror" />
                @error('lastName')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
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
            <div class="rounded border bg-gray-50 px-3 py-1.5 text-sm text-gray-700">
                <span class="block text-xs text-gray-500">NIC, date of birth and gender</span>
                <span class="font-mono text-xs">{{ $person->nic ?: '—' }}</span>
                · {{ $person->date_of_birth->format('Y-m-d') }}
                · {{ ucfirst(strtolower($person->gender->value)) }}
            </div>
        </div>
    </div>

    <div class="rounded bg-white p-5 shadow">
        <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Contacts</h3>
        @if($person->contacts->isNotEmpty())
            <ul class="mb-4 divide-y text-sm">
                @foreach($person->contacts as $contact)
                    <li class="flex items-center gap-3 py-1.5">
                        <span class="w-28 text-xs text-gray-500">{{ ucfirst(strtolower(str_replace('_', ' ', $contact->type->value))) }}</span>
                        <span class="text-gray-700">{{ $contact->value }}</span>
                        @if($contact->is_primary)
                            <span class="rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">Primary</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mb-4 text-sm text-gray-500">No contacts recorded.</p>
        @endif

        @if($canEdit)
            <div class="grid grid-cols-1 items-end gap-4 md:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Add contact</label>
                    <select wire:model="newContactType" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                        @foreach($contactTypes as $contactType)
                            <option value="{{ $contactType->value }}">{{ ucfirst(strtolower(str_replace('_', ' ', $contactType->value))) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <input wire:model="newContactValue" type="text" maxlength="150" placeholder="Number or address"
                        class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('newContactValue') border-red-400 @enderror" />
                    @error('newContactValue')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-center gap-2 pb-1.5 text-sm text-gray-700">
                    <input type="checkbox" wire:model="newContactPrimary" class="rounded border" /> Primary
                </label>
            </div>
        @endif
    </div>

    <div class="rounded bg-white p-5 shadow">
        <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Addresses</h3>
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            @foreach(['PERMANENT' => 'Permanent address', 'CURRENT' => 'Current address'] as $type => $label)
                <div class="grid grid-cols-1 gap-3">
                    <p class="text-xs font-semibold text-gray-600">{{ $label }}</p>
                    <div>
                        <input wire:model="addresses.{{ $type }}.line1" type="text" maxlength="150" placeholder="Address line 1" @disabled(! $canEdit)
                            class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error("addresses.$type.line1") border-red-400 @enderror" />
                        @error("addresses.$type.line1")<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <input wire:model="addresses.{{ $type }}.line2" type="text" maxlength="150" placeholder="Address line 2" @disabled(! $canEdit)
                        class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500" />
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <input wire:model="addresses.{{ $type }}.city" type="text" maxlength="80" placeholder="City" @disabled(! $canEdit)
                                class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error("addresses.$type.city") border-red-400 @enderror" />
                            @error("addresses.$type.city")<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <input wire:model="addresses.{{ $type }}.district" type="text" maxlength="50" placeholder="District" @disabled(! $canEdit)
                            class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500" />
                        <input wire:model="addresses.{{ $type }}.postal_code" type="text" maxlength="10" placeholder="Postal code" @disabled(! $canEdit)
                            class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500" />
                    </div>
                </div>
            @endforeach
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
                            <td class="space-x-2 whitespace-nowrap px-3 py-2 text-right">
                                <button type="button" wire:click="openEditEmergency({{ $contact->id }})" class="text-blue-600 hover:underline">Edit</button>
                                <button type="button" wire:click="removeChild('emergency', {{ $contact->id }})"
                                    wire:confirm="Remove this emergency contact?" class="text-red-600 hover:text-red-800">Remove</button>
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
                            <td class="space-x-2 whitespace-nowrap px-3 py-2 text-right">
                                <button type="button" wire:click="openEditDependent({{ $dependent->id }})" class="text-blue-600 hover:underline">Edit</button>
                                <button type="button" wire:click="removeChild('dependent', {{ $dependent->id }})"
                                    wire:confirm="Remove this dependent?" class="text-red-600 hover:text-red-800">Remove</button>
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
                            <td class="space-x-2 whitespace-nowrap px-3 py-2 text-right">
                                <button type="button" wire:click="openEditQualification({{ $qualification->id }})" class="text-blue-600 hover:underline">Edit</button>
                                <button type="button" wire:click="removeChild('qualification', {{ $qualification->id }})"
                                    wire:confirm="Remove this qualification?" class="text-red-600 hover:text-red-800">Remove</button>
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
                            <td class="space-x-2 whitespace-nowrap px-3 py-2 text-right">
                                <button type="button" wire:click="openEditExperience({{ $experience->id }})" class="text-blue-600 hover:underline">Edit</button>
                                <button type="button" wire:click="removeChild('experience', {{ $experience->id }})"
                                    wire:confirm="Remove this experience?" class="text-red-600 hover:text-red-800">Remove</button>
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
                            <input type="text" wire:model="childRelationship" maxlength="50" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('childRelationship') border-red-400 @enderror" />
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
                            <input type="text" wire:model="childRelationship" maxlength="50" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('childRelationship') border-red-400 @enderror" />
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
