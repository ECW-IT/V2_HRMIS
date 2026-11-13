<div class="p-4">
    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-gray-800">Users</h1>
            <p class="text-sm text-gray-500">Create users and limit them to companies.</p>
        </div>
        <button
            type="button"
            wire:click="openCreate"
            class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500"
        >
            New user
        </button>
    </div>

    <input
        type="search"
        wire:model.live.debounce.300ms="search"
        placeholder="Search users"
        class="mb-3 w-full max-w-sm rounded border border-gray-300 px-3 py-2 text-sm"
    >

    <div class="overflow-hidden rounded border bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Email</th>
                    <th class="px-3 py-2">Roles</th>
                    <th class="px-3 py-2">Companies</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr class="border-t">
                        <td class="px-3 py-2">{{ $user->name }}</td>
                        <td class="px-3 py-2">{{ $user->email }}</td>
                        <td class="px-3 py-2">{{ $user->roles->pluck('name')->join(', ') }}</td>
                        <td class="px-3 py-2">
                            {{ $user->all_companies ? 'All companies' : $user->companies->pluck('name')->join(', ') }}
                        </td>
                        <td class="px-3 py-2">{{ $user->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-3 py-2 text-right">
                            <button type="button" wire:click="openEdit({{ $user->id }})" class="text-indigo-600 hover:underline">
                                Edit
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-gray-500">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-4 shadow-xl">
                <h2 class="text-base font-semibold text-gray-800">{{ $editingId ? 'Edit user' : 'New user' }}</h2>

                <div class="mt-3 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Name</label>
                        <input type="text" wire:model="formName" class="w-full rounded border px-3 py-2 text-sm">
                        @error('formName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Email</label>
                        <input type="email" wire:model="formEmail" class="w-full rounded border px-3 py-2 text-sm">
                        @error('formEmail') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Password</label>
                        <input type="password" wire:model="formPassword" class="w-full rounded border px-3 py-2 text-sm">
                        @error('formPassword') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model.live="formAllCompanies">
                        All companies
                    </label>
                    @unless ($formAllCompanies)
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Companies</label>
                            <select wire:model="formCompanyIds" multiple class="h-28 w-full rounded border px-3 py-2 text-sm">
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                                @endforeach
                            </select>
                            @error('formCompanyIds') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endunless
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Roles</label>
                        <select wire:model="formRoles" multiple class="h-28 w-full rounded border px-3 py-2 text-sm">
                            @foreach ($roles as $role)
                                <option value="{{ $role }}">{{ $role }}</option>
                            @endforeach
                        </select>
                        @error('formRoles') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="formIsActive">
                        Active
                    </label>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                    <button type="button" wire:click="save" class="rounded bg-indigo-600 px-3 py-1.5 text-sm text-white">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
