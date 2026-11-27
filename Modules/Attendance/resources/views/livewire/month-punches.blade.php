<div class="mx-auto max-w-6xl px-6 py-8">
    <div class="mb-4">
        <h1 class="text-lg font-semibold text-slate-800">Month entry</h1>
        <p class="text-sm text-slate-500">Enter in and out times for one employee or a department. Holiday dates are highlighted.</p>
    </div>

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <label class="text-xs text-slate-500">Mode
            <select wire:model.live="mode" class="mt-1 block rounded border px-3 py-1.5 text-sm">
                <option value="employee">Employee</option>
                <option value="department">Department</option>
            </select>
        </label>
        <label class="text-xs text-slate-500">Month
            <input type="month" wire:model="month" class="mt-1 block rounded border px-3 py-1.5 text-sm">
        </label>
        @if ($mode === 'employee')
            <label class="text-xs text-slate-500">Employee number
                <div class="mt-1 w-72">
                    <x-searchable-select
                        model="employeeNo"
                        :value="$employeeNo"
                        :options="$employeeOptions"
                        empty-label="Select"
                        aria-label="Employee number"
                        search-placeholder="Search number or name"
                        input-class="w-full rounded border px-3 py-1.5 text-sm"
                    />
                </div>
            </label>
        @else
            <label class="text-xs text-slate-500">Department
                <select wire:model="departmentId" class="mt-1 block rounded border px-3 py-1.5 text-sm">
                    <option value="">Select</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <button type="button" wire:click="loadMonth" class="rounded border px-3 py-1.5 text-sm">Show month</button>
        @if ($canAdjust && $loaded && ! $locked)
            <button type="button" wire:click="saveMonth" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">Save</button>
        @endif
    </div>

    @if ($notice !== '')
        <p class="mb-4 text-sm text-slate-700">{{ $notice }}</p>
    @endif

    @foreach ($rows as $index => $row)
        <section class="mb-8" wire:key="emp-{{ $row['employment_id'] }}">
            <h2 class="mb-2 text-sm font-semibold text-slate-800">{{ $row['employee_no'] }} · {{ $row['name'] }}</h2>
            @if ($row['enrolment_missing'])
                <p class="mb-2 text-xs text-amber-700">No fingerprint enrolment, so times for this employee cannot be saved.</p>
            @endif
            <div class="overflow-hidden rounded border bg-white">
                <table class="table-compact min-w-full text-left">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">In</th>
                            <th class="px-3 py-2">Out</th>
                            <th class="px-3 py-2">Holiday</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($row['days'] as $dayIndex => $day)
                            <tr class="border-t {{ $day['holiday'] !== '' ? 'bg-amber-50' : '' }}" wire:key="day-{{ $row['employment_id'] }}-{{ $day['date'] }}">
                                <td class="px-3 py-2">{{ $day['date'] }}</td>
                                <td class="px-3 py-2">
                                    <input type="time" step="1" wire:model="rows.{{ $index }}.days.{{ $dayIndex }}.in" class="rounded border px-2 py-1 text-sm" @disabled($locked || ! $canAdjust)>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="time" step="1" wire:model="rows.{{ $index }}.days.{{ $dayIndex }}.out" class="rounded border px-2 py-1 text-sm" @disabled($locked || ! $canAdjust)>
                                </td>
                                <td class="px-3 py-2 text-amber-800">{{ $day['holiday'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
</div>
