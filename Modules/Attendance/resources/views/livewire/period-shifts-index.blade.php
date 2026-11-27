<div class="p-4">
    <div class="mb-4">
        <h1 class="text-lg font-semibold text-slate-800">Pay month shifts</h1>
        <p class="text-sm text-slate-500">Set shift times, late, overtime and breaks from the first of a pay month. The change stays until the next one.</p>
    </div>

    @if ($companyId === null)
        <div class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            Select a company first (use the company dropdown in the top bar).
        </div>
    @else
        @if ($formError !== '')
            <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
        @endif

        <div class="mb-4 grid grid-cols-1 gap-3 rounded border bg-white p-3 md:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Pay month</label>
                <select wire:model.live="payPeriodId" class="w-full rounded border px-2 py-1.5 text-sm">
                    @foreach ($periods as $row)
                        <option value="{{ $row->id }}">{{ $row->start_date->format('F Y') }} ({{ $row->status->value }})</option>
                    @endforeach
                </select>
                @if ($period)
                    <p class="mt-1 text-xs text-slate-500">Starts {{ $period->start_date->format('Y-m-d') }} and stays in force until the next change.</p>
                @endif
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Apply to</label>
                <select wire:model.live="mode" class="w-full rounded border px-2 py-1.5 text-sm">
                    <option value="departments">Departments</option>
                    <option value="employees">Employees</option>
                </select>
            </div>
        </div>

        @if ($mode === 'departments')
            <div class="mb-4 rounded border bg-white p-3">
                <h2 class="mb-2 text-sm font-semibold text-slate-800">Departments</h2>
                <p class="mb-2 text-xs text-slate-500">A department includes the sections under it. Only people assigned there on the 1st are updated.</p>
                <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                    @forelse ($departments as $department)
                        <label class="flex items-center gap-2 text-sm" wire:key="dept-{{ $department->id }}">
                            <input type="checkbox" value="{{ $department->id }}" wire:model="selectedDepartments">
                            {{ $department->name }}
                        </label>
                    @empty
                        <p class="text-sm text-slate-500">No departments.</p>
                    @endforelse
                </div>
            </div>
        @else
            <div class="mb-4 grid grid-cols-1 gap-3 rounded border bg-white p-3 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Org unit</label>
                    <select wire:model.live="filterOrgUnitId" class="w-full rounded border px-2 py-1.5 text-sm">
                        <option value="">All</option>
                        @foreach ($orgUnits as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Grade</label>
                    <select wire:model.live="filterGradeId" class="w-full rounded border px-2 py-1.5 text-sm">
                        <option value="">All</option>
                        @foreach ($grades as $grade)
                            <option value="{{ $grade->id }}">{{ $grade->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Shift on the 1st</label>
                    <select wire:model.live="filterShiftId" class="w-full rounded border px-2 py-1.5 text-sm">
                        <option value="">All</option>
                        @foreach ($shifts as $shift)
                            <option value="{{ $shift->id }}">{{ $shift->code }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mb-4 overflow-hidden rounded border bg-white">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2"></th>
                            <th class="px-3 py-2">Employee</th>
                            <th class="px-3 py-2">Shift on the 1st</th>
                            <th class="px-3 py-2">Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            <tr class="border-t" wire:key="emp-{{ $employee->id }}">
                                <td class="px-3 py-2">
                                    <input type="checkbox" wire:click="toggle({{ $employee->id }})" @checked(in_array($employee->id, $selected, true))>
                                </td>
                                <td class="px-3 py-2">{{ $employee->employee_no }} · {{ $employee->person->name_with_initials }}</td>
                                <td class="px-3 py-2">{{ ($currentShift)($employee)?->code ?? '—' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $results[$employee->id] ?? '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">No employees match.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if ($mode === 'departments' && $results !== [])
            <div class="mb-4 rounded border bg-white p-3 text-sm">
                <h2 class="mb-2 text-sm font-semibold text-slate-800">Results</h2>
                <ul class="space-y-1">
                    @foreach ($results as $employmentId => $line)
                        <li wire:key="result-{{ $employmentId }}">{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded border bg-white p-4">
            <h2 class="mb-3 text-sm font-semibold text-slate-800">Shift for this month</h2>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Shift</label>
                    <select wire:model.live="shift_id" class="w-full rounded border px-2 py-1.5 text-sm">
                        <option value="">Select</option>
                        @foreach ($shifts as $shift)
                            <option value="{{ $shift->id }}">{{ $shift->code }} — {{ $shift->name }}</option>
                        @endforeach
                    </select>
                    @error('shift_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">OT mode</label>
                    <select wire:model="ot_mode" class="w-full rounded border px-2 py-1.5 text-sm">
                        @foreach ($otModes as $otMode)
                            <option value="{{ $otMode->value }}">{{ $otMode->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-4">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="late_deduction"> Late deduction</label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="breaks_apply"> Breaks apply</label>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Start</label>
                    <input type="time" wire:model="start_time" class="w-full rounded border px-2 py-1.5 text-sm">
                    @error('start_time') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">End</label>
                    <input type="time" wire:model="end_time" class="w-full rounded border px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Late from</label>
                    <input type="time" wire:model="late_from" class="w-full rounded border px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Morning cut-off</label>
                    <input type="time" wire:model="morning_cutoff" class="w-full rounded border px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Half day from</label>
                    <input type="time" wire:model="half_day_from" class="w-full rounded border px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Early leave from</label>
                    <input type="time" wire:model="early_leave_from" class="w-full rounded border px-2 py-1.5 text-sm">
                </div>
            </div>

            <div class="mt-4">
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-700">Breaks</h3>
                    <button type="button" wire:click="addBreakRow" class="text-sm text-[var(--color-erp-accent)] hover:underline">Add break</button>
                </div>
                @foreach ($breaks as $index => $break)
                    <div class="mb-2 grid grid-cols-1 gap-2 rounded border p-2 md:grid-cols-4" wire:key="period-break-{{ $index }}">
                        <input type="text" wire:model="breaks.{{ $index }}.name" placeholder="Name" class="rounded border px-2 py-1 text-sm">
                        <input type="time" wire:model="breaks.{{ $index }}.start_time" class="rounded border px-2 py-1 text-sm">
                        <input type="time" wire:model="breaks.{{ $index }}.end_time" class="rounded border px-2 py-1 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <label class="flex items-center gap-1 text-xs"><input type="checkbox" wire:model="breaks.{{ $index }}.is_paid"> Paid</label>
                            <button type="button" wire:click="removeBreakRow({{ $index }})" class="text-xs text-red-600">Remove</button>
                        </div>
                    </div>
                @endforeach
                @error('breaks.*.name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <button type="button" wire:click="apply" class="mt-3 rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Apply</button>
        </div>
    @endif
</div>
