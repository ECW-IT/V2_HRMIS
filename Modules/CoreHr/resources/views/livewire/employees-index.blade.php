<div class="mx-auto max-w-[1400px] px-6 py-4">
    @if(session('success'))
        <div class="mb-4 rounded bg-green-100 px-4 py-2 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded bg-red-100 px-4 py-2 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-end gap-3 rounded bg-white p-3 shadow">
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600" for="search">Search</label>
            <input id="search" type="text" wire:model.live.debounce.400ms="search" placeholder="Number, name or NIC"
                class="w-56 rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500" />
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Department / section</label>
            <x-searchable-select
                model="orgUnitId"
                :value="$orgUnitId"
                :options="$orgUnits"
                empty-label="All"
                aria-label="Department / section"
                search-placeholder="Search department"
                input-class="w-52 rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Designation</label>
            <x-searchable-select
                model="designationId"
                :value="$designationId"
                :options="$designations"
                empty-label="All"
                aria-label="Designation"
                search-placeholder="Search designation"
                input-class="w-44 rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600" for="employeeCategoryId">Category</label>
            <select id="employeeCategoryId" wire:model.live="employeeCategoryId" class="w-40 rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                <option value="">All</option>
                @foreach($employeeCategories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600" for="type">Type</label>
            <select id="type" wire:model.live="type" class="w-32 rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                <option value="">All</option>
                @foreach($types as $employmentType)
                    <option value="{{ $employmentType->value }}">{{ ucfirst(strtolower($employmentType->value)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600" for="status">Status</label>
            <select id="status" wire:model.live="status" class="w-32 rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @if($showPayrollActiveFilter)
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600" for="payrollActive">Payroll active</label>
                <select id="payrollActive" wire:model.live="payrollActive" class="w-36 rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        @endif

        <div class="ml-auto flex items-center gap-2">
            <button type="button" wire:click="export" wire:loading.attr="disabled" wire:target="export"
                class="rounded bg-green-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                Export to Excel
            </button>
            @if($showHireButton)
                @can('employee.create')
                    <a href="{{ route('employees.create') }}" class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                        Add employee
                    </a>
                @endcan
            @endif
        </div>
    </div>

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="w-full table-compact text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-center text-xs font-semibold text-gray-600">Profile</th>

                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">
                        <button type="button" wire:click="sort('employee_no')" class="hover:underline">
                            Employee no. @if($sortBy === 'employee_no'){{ $sortDir === 'asc' ? '▲' : '▼' }}@endif
                        </button>
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">EPF no</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">
                        <button type="button" wire:click="sort('name')" class="hover:underline">
                            Name @if($sortBy === 'name'){{ $sortDir === 'asc' ? '▲' : '▼' }}@endif
                        </button>
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Department / subsection</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">
                        <button type="button" wire:click="sort('hire_date')" class="hover:underline">
                            Hire date @if($sortBy === 'hire_date'){{ $sortDir === 'asc' ? '▲' : '▼' }}@endif
                        </button>
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Service period</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Designation</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Type</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($employees as $employee)
                    <tr class="cursor-pointer hover:bg-[var(--color-erp-row-hover)]"
                        onclick="window.location='{{ route('employees.show', $employee) }}'">
                        <td class="px-3 py-1.5 text-center" onclick="event.stopPropagation()">
                            <a href="{{ route('employees.show', $employee) }}" class="inline-flex" title="View profile">
                                @if($employee->photo_path)
                                    <img wire:key="list-photo-{{ $employee->id }}-{{ $employee->photo_path }}"
                                        src="{{ route('persons.photo', $employee->person_id) }}?v={{ urlencode((string) $employee->photo_path) }}"
                                        alt="{{ $employee->name_with_initials }}"
                                        class="h-8 w-8 rounded-full object-cover ring-1 ring-gray-200" />
                                @else
                                    <span wire:key="list-photo-{{ $employee->id }}-none"
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--color-erp-accent-soft)] text-xs font-semibold text-[var(--color-erp-accent)]">
                                        {{ mb_strtoupper(mb_substr((string) $employee->first_name, 0, 1).mb_substr((string) $employee->last_name, 0, 1)) }}
                                    </span>
                                @endif
                            </a>
                        </td>
                        <td class="px-3 py-1.5 font-mono text-xs">
                            <a href="{{ route('employees.show', $employee) }}" class="text-blue-600 hover:underline">{{ $employee->employee_no }}</a>
                        </td>
                        <td class="px-3 py-1.5 font-mono text-xs text-gray-600">{{ $employee->epf_no ?: '—' }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $employee->name_with_initials }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-600">
                            @if($employee->parent_unit_name)
                                {{ $employee->parent_unit_name }} / {{ $employee->department_name }}
                            @else
                                {{ $employee->department_name ?: '—' }}
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-xs text-gray-600">{{ $employee->hire_date->format('Y-m-d') }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-600">{{ $this->servicePeriod($employee) }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-600">{{ $employee->designation_name ?: '—' }}</td>
                        <td class="px-3 py-1.5">@include('corehr::partials.type-badge', ['type' => $employee->type])</td>
                        <td class="px-3 py-1.5 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                            <div class="inline-flex items-center gap-1.5">
                                <x-action-icon-button
                                    variant="view"
                                    href="{{ route('employees.show', $employee) }}"
                                />
                                @can('employee.update')
                                    @unless($employee->isResigned())
                                        <x-action-icon-button
                                            variant="edit"
                                            wire:click="openEdit({{ $employee->id }})"
                                        />
                                    @endunless
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-3 py-6 text-center text-sm text-gray-500">{{ $emptyMessage }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $employees->links() }}
    </div>

    @if($editing)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4">
            <div class="my-6 w-full max-w-4xl rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b px-5 py-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-800">Edit employee</h2>
                        <p class="text-xs text-gray-500">
                            <span class="font-mono">{{ $editing->employee_no }}</span>
                            · {{ $editing->person->name_with_initials }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeEdit" class="rounded border px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Close</button>
                </div>

                <div class="border-b px-5 py-3">
                    <ol class="flex flex-wrap gap-2 text-xs">
                        @foreach($editSteps as $number => $label)
                            <li>
                                <button type="button" wire:click="setEditStep({{ $number }})"
                                    class="rounded-full px-3 py-1 {{ $editStep === $number ? 'bg-blue-600 text-white' : ($editStep > $number ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500') }}">
                                    {{ $number }}. {{ $label }}
                                </button>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <div class="max-h-[70vh] overflow-y-auto p-5">
                    @if($editStep === 1)
                        <livewire:corehr.tabs.personal :employment="$editing" :key="'edit-personal-'.$editing->id" />
                    @elseif($editStep === 2)
                        <livewire:corehr.tabs.edit-employment-step :employment="$editing" :key="'edit-employment-'.$editing->id" />
                    @else
                        @can('compensation.view')
                            <livewire:payroll.employee-compensation-panel :employment="$editing" :key="'edit-pay-'.$editing->id" />
                        @else
                            <p class="mb-6 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                No permission to view compensation.
                            </p>
                        @endcan

                        @can('employee.bank.view')
                            <livewire:corehr.tabs.bank :employment="$editing" :key="'edit-bank-'.$editing->id" />
                        @else
                            <p class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                You do not have permission to view bank details.
                            </p>
                        @endcan
                    @endif
                </div>

                <div class="flex items-center justify-between border-t px-5 py-3">
                    <button type="button" wire:click="setEditStep({{ max(1, $editStep - 1) }})"
                        class="rounded border px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 {{ $editStep === 1 ? 'invisible' : '' }}">
                        Back
                    </button>
                    @if($editStep < 3)
                        <button type="button" wire:click="setEditStep({{ $editStep + 1 }})"
                            class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                            Next
                        </button>
                    @else
                        <button type="button" wire:click="closeEdit"
                            class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                            Done
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
