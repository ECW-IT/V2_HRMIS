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
            <label class="mb-1 block text-xs font-medium text-gray-600" for="orgUnitId">Department / section</label>
            <select id="orgUnitId" wire:model.live="orgUnitId" class="w-52 rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                <option value="">All</option>
                @foreach($orgUnits as $unit)
                    <option value="{{ $unit['id'] }}">{{ $unit['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600" for="jobId">Job</label>
            <select id="jobId" wire:model.live="jobId" class="w-44 rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                <option value="">All</option>
                @foreach($jobs as $job)
                    <option value="{{ $job->id }}">{{ $job->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600" for="gradeId">Grade</label>
            <select id="gradeId" wire:model.live="gradeId" class="w-32 rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                <option value="">All</option>
                @foreach($grades as $grade)
                    <option value="{{ $grade->id }}">{{ $grade->name }}</option>
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

        <div class="ml-auto flex items-center gap-2">
            <button type="button" wire:click="export" wire:loading.attr="disabled" wire:target="export"
                class="rounded bg-green-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                Export to Excel
            </button>
            @can('employee.create')
                <a href="{{ route('employees.create') }}" class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                    Hire employee
                </a>
            @endcan
        </div>
    </div>

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="w-full table-compact text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">
                        <button type="button" wire:click="sort('employee_no')" class="hover:underline">
                            Employee no. @if($sortBy === 'employee_no'){{ $sortDir === 'asc' ? '▲' : '▼' }}@endif
                        </button>
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">
                        <button type="button" wire:click="sort('name')" class="hover:underline">
                            Name @if($sortBy === 'name'){{ $sortDir === 'asc' ? '▲' : '▼' }}@endif
                        </button>
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Department / section</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Job</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Grade</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Type</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">
                        <button type="button" wire:click="sort('hire_date')" class="hover:underline">
                            Hire date @if($sortBy === 'hire_date'){{ $sortDir === 'asc' ? '▲' : '▼' }}@endif
                        </button>
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Status</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold text-gray-600">Ready</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($employees as $employee)
                    <tr class="cursor-pointer hover:bg-[var(--color-erp-row-hover)]"
                        onclick="window.location='{{ route('employees.show', $employee) }}'">
                        <td class="px-3 py-1.5 font-mono text-xs">
                            <a href="{{ route('employees.show', $employee) }}" class="text-blue-600 hover:underline">{{ $employee->employee_no }}</a>
                        </td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $employee->name_with_initials }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-600">
                            @if($employee->parent_unit_name)
                                {{ $employee->parent_unit_name }} / {{ $employee->department_name }}
                            @else
                                {{ $employee->department_name ?: '—' }}
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-xs text-gray-600">{{ $employee->job_name ?: '—' }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-600">{{ $employee->grade_name ?: '—' }}</td>
                        <td class="px-3 py-1.5">@include('corehr::partials.type-badge', ['type' => $employee->type])</td>
                        <td class="px-3 py-1.5 text-xs text-gray-600">{{ $employee->hire_date->format('Y-m-d') }}</td>
                        <td class="px-3 py-1.5">@include('corehr::partials.status-badge', ['status' => $employee->status])</td>
                        <td class="px-3 py-1.5 text-center">
                            @if(in_array($employee->id, $blockerIds, true))
                                <span class="inline-block h-2.5 w-2.5 rounded-full bg-red-500" title="Has a payroll blocker today"></span>
                                <span class="sr-only">Not ready</span>
                            @else
                                <span class="text-xs text-green-600">✓</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-3 py-6 text-center text-sm text-gray-500">No employees match these filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $employees->links() }}
    </div>
</div>
