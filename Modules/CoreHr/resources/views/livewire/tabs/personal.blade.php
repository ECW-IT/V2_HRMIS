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

    <div class="rounded bg-white p-5 shadow">
        <h3 class="mb-3 border-b pb-2 text-sm font-semibold text-gray-700">Emergency contacts</h3>
        @forelse($person->emergencyContacts as $contact)
            <p class="py-1 text-sm text-gray-700">{{ $contact->name }} <span class="text-xs text-gray-500">({{ $contact->relationship }})</span> · {{ $contact->phone }}</p>
        @empty
            <p class="text-sm text-gray-500">None recorded.</p>
        @endforelse
    </div>

    <div class="rounded bg-white p-5 shadow">
        <h3 class="mb-3 border-b pb-2 text-sm font-semibold text-gray-700">Dependents</h3>
        @forelse($person->dependents as $dependent)
            <p class="py-1 text-sm text-gray-700">{{ $dependent->name }} <span class="text-xs text-gray-500">({{ $dependent->relationship }})</span>
                @if($dependent->date_of_birth) · {{ $dependent->date_of_birth->format('Y-m-d') }} @endif</p>
        @empty
            <p class="text-sm text-gray-500">None recorded.</p>
        @endforelse
    </div>

    <div class="rounded bg-white p-5 shadow">
        <h3 class="mb-3 border-b pb-2 text-sm font-semibold text-gray-700">Qualifications</h3>
        @forelse($person->qualifications as $qualification)
            <p class="py-1 text-sm text-gray-700">{{ $qualification->title }} <span class="text-xs text-gray-500">({{ $qualification->type }})</span> · {{ $qualification->institute }}@if($qualification->year), {{ $qualification->year }}@endif</p>
        @empty
            <p class="text-sm text-gray-500">None recorded.</p>
        @endforelse
    </div>

    <div class="rounded bg-white p-5 shadow">
        <h3 class="mb-3 border-b pb-2 text-sm font-semibold text-gray-700">Experience</h3>
        @forelse($person->experience as $experience)
            <p class="py-1 text-sm text-gray-700">{{ $experience->job_title }} · {{ $experience->employer }}
                <span class="text-xs text-gray-500">{{ $experience->from_date->format('Y-m-d') }} to {{ $experience->to_date?->format('Y-m-d') ?? 'present' }}</span></p>
        @empty
            <p class="text-sm text-gray-500">None recorded.</p>
        @endforelse
    </div>
</div>
