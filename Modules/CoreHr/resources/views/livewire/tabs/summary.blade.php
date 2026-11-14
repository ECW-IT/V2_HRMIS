<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div class="rounded bg-white p-5 shadow">
        <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Personal</h3>
        <dl class="grid grid-cols-3 gap-y-2 text-sm">
            <dt class="text-xs text-gray-500">NIC</dt>
            <dd class="col-span-2 font-mono text-xs text-gray-700">{{ $person->nic ?: '—' }}</dd>
            @if($person->passport_no)
                <dt class="text-xs text-gray-500">Passport no.</dt>
                <dd class="col-span-2 font-mono text-xs text-gray-700">{{ $person->passport_no }}</dd>
            @endif
            <dt class="text-xs text-gray-500">Date of birth</dt>
            <dd class="col-span-2 text-gray-700">{{ $person->date_of_birth->format('Y-m-d') }}</dd>
            <dt class="text-xs text-gray-500">Age</dt>
            <dd class="col-span-2 text-gray-700">{{ $age }}</dd>
            <dt class="text-xs text-gray-500">Gender</dt>
            <dd class="col-span-2 text-gray-700">{{ ucfirst(strtolower($person->gender->value)) }}</dd>
            <dt class="text-xs text-gray-500">Mobile</dt>
            <dd class="col-span-2 text-gray-700">{{ $mobile ?: '—' }}</dd>
        </dl>
    </div>

    <div class="rounded bg-white p-5 shadow">
        <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Employment</h3>
        <dl class="grid grid-cols-3 gap-y-2 text-sm">
            <dt class="text-xs text-gray-500">Type</dt>
            <dd class="col-span-2 text-gray-700">{{ ucfirst(strtolower($employment->type->value)) }}</dd>
            <dt class="text-xs text-gray-500">Employee category</dt>
            <dd class="col-span-2 text-gray-700">{{ $employment->employeeCategory?->name ?? '—' }}</dd>
            <dt class="text-xs text-gray-500">Office / shop category</dt>
            <dd class="col-span-2 text-gray-700">
                @can('employee.update')
                    <div class="flex flex-wrap items-center gap-2">
                        <select wire:model="staffWorkCategoryId" class="rounded border border-gray-300 px-2 py-1 text-sm">
                            <option value="">None</option>
                            @foreach ($staffWorkCategories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }} ({{ $category->code }})</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="saveStaffWorkCategory" class="text-xs text-[var(--color-erp-accent)] hover:underline">Save</button>
                    </div>
                    @error('staffWorkCategoryId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @if ($staffWorkMessage !== '')
                        <p class="mt-1 text-xs text-emerald-600">{{ $staffWorkMessage }}</p>
                    @endif
                @else
                    {{ $employment->staffWorkCategory?->name ?? '—' }}
                @endcan
            </dd>
            <dt class="text-xs text-gray-500">Hire date</dt>
            <dd class="col-span-2 text-gray-700">{{ $employment->hire_date->format('Y-m-d') }}</dd>
            @if($employment->confirmed_on)
                <dt class="text-xs text-gray-500">Made permanent on</dt>
                <dd class="col-span-2 text-gray-700">{{ $employment->confirmed_on->format('Y-m-d') }}</dd>
            @elseif($employment->confirmation_due_date)
                <dt class="text-xs text-gray-500">Probation ends</dt>
                <dd class="col-span-2 text-gray-700">{{ $employment->confirmation_due_date->format('Y-m-d') }}</dd>
            @endif
            <dt class="text-xs text-gray-500">EPF</dt>
            <dd class="col-span-2 text-gray-700">
                @if($employment->epf_liable)
                    Liable · <span class="font-mono text-xs">{{ $employment->epf_no ?: '—' }}</span>
                @else
                    Not liable
                @endif
            </dd>
            <dt class="text-xs text-gray-500">Grade</dt>
            <dd class="col-span-2 text-gray-700">{{ $assignment?->grade?->name ?? '—' }}</dd>
            <dt class="text-xs text-gray-500">Location</dt>
            <dd class="col-span-2 text-gray-700">{{ $assignment?->location?->name ?? '—' }}</dd>
            <dt class="text-xs text-gray-500">Reports to</dt>
            <dd class="col-span-2 text-gray-700">
                @if($assignment?->manager)
                    <a href="{{ route('employees.show', $assignment->manager) }}" class="text-blue-600 hover:underline">
                        {{ $assignment->manager->person->name_with_initials }}
                    </a>
                    <span class="font-mono text-xs text-gray-500">{{ $assignment->manager->employee_no }}</span>
                @else
                    —
                @endif
            </dd>
            @if($employment->exit_date)
                <dt class="text-xs text-gray-500">Left on</dt>
                <dd class="col-span-2 text-gray-700">{{ $employment->exit_date->format('Y-m-d') }}</dd>
                <dt class="text-xs text-gray-500">Reason for leaving</dt>
                <dd class="col-span-2 text-gray-700">{{ $employment->exit_reason ? ucfirst(strtolower(str_replace('_', ' ', $employment->exit_reason->value))) : '—' }}</dd>
            @endif
        </dl>
    </div>

    <div class="rounded bg-white p-5 shadow md:col-span-2">
        <h3 class="mb-3 border-b pb-2 text-sm font-semibold text-gray-700">Readiness today</h3>
        @if($issues->isEmpty())
            <span class="rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Ready</span>
        @else
            <ul class="space-y-1 text-sm">
                @foreach($issues as $issue)
                    <li class="flex items-center gap-2">
                        <span @class([
                            'rounded px-2 py-0.5 text-xs font-medium',
                            'bg-red-100 text-red-700' => $issue->severity === \Modules\CoreHr\Readiness\Severity::BLOCKER,
                            'bg-yellow-100 text-yellow-700' => $issue->severity !== \Modules\CoreHr\Readiness\Severity::BLOCKER,
                        ])>{{ $issue->code }}</span>
                        <span class="text-gray-700">{{ $issue->title }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if($others->isNotEmpty())
        <div class="rounded bg-white p-5 shadow md:col-span-2">
            <h3 class="mb-3 border-b pb-2 text-sm font-semibold text-gray-700">Other employments</h3>
            <table class="w-full table-compact text-sm">
                <thead class="border-b bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Company</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Employee no.</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">From</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">To</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($others as $other)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-1.5 text-xs text-gray-600">{{ $other->company->name }}</td>
                            <td class="px-3 py-1.5 font-mono text-xs">
                                <a href="{{ route('employees.show', $other) }}" class="text-blue-600 hover:underline">{{ $other->employee_no }}</a>
                            </td>
                            <td class="px-3 py-1.5 text-xs text-gray-600">{{ $other->hire_date->format('Y-m-d') }}</td>
                            <td class="px-3 py-1.5 text-xs text-gray-600">{{ $other->exit_date?->format('Y-m-d') ?? 'Present' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
