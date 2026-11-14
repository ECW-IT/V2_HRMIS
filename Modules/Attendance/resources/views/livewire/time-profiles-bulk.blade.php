<div class="p-4">
    <div class="mb-4">
        <h1 class="text-lg font-semibold text-slate-800">Bulk time profiles</h1>
        <p class="text-sm text-slate-500">Change shift and time rules for several employees at once.</p>
    </div>

    @if ($companyId === null)
        <div class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            Select a company first (use the company dropdown in the top bar).
        </div>
    @else
        <div class="mb-4 grid grid-cols-1 gap-3 rounded border bg-white p-3 md:grid-cols-4">
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
                <label class="mb-1 block text-xs font-medium text-slate-600">Current shift</label>
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
                        <th class="px-3 py-2">Current shift</th>
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

        <div class="rounded border bg-white p-4">
            <h2 class="mb-3 text-sm font-semibold text-slate-800">Apply to selected</h2>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Effective from</label>
                    <input type="date" wire:model="effective_from" class="w-full rounded border px-2 py-1.5 text-sm">
                    @error('effective_from') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('selected') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Shift</label>
                    <select wire:model="shift_id" class="w-full rounded border px-2 py-1.5 text-sm">
                        <option value="">Select</option>
                        @foreach ($shifts as $shift)
                            <option value="{{ $shift->id }}">{{ $shift->code }} — {{ $shift->name }}</option>
                        @endforeach
                    </select>
                    @error('shift_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Attendance mode</label>
                    <select wire:model="attendance_mode" class="w-full rounded border px-2 py-1.5 text-sm">
                        @foreach ($attendanceModes as $mode)
                            <option value="{{ $mode->value }}">{{ $mode->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">OT mode</label>
                    <select wire:model="ot_mode" class="w-full rounded border px-2 py-1.5 text-sm">
                        @foreach ($otModes as $mode)
                            <option value="{{ $mode->value }}">{{ $mode->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="late_deduction"> Late deduction</label>
                </div>
            </div>
            <button type="button" wire:click="apply" class="mt-3 rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Apply</button>
        </div>
    @endif
</div>
