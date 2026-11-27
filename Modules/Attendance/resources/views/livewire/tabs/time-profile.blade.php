<div class="rounded bg-white p-5 shadow">
    <div class="mb-4 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-700">Time profile</h3>
        @can('employee.update')
            <button type="button" wire:click="openChange" class="rounded border border-blue-600 px-3 py-1.5 text-sm text-blue-600 hover:bg-blue-50">Change</button>
        @endcan
    </div>

    @if ($message !== '')
        <div class="mb-3 rounded bg-green-100 px-3 py-2 text-sm text-green-700">{{ $message }}</div>
    @endif

    <div class="mb-6">
        <h4 class="mb-2 text-xs font-semibold uppercase text-gray-500">Current</h4>
        @if ($current)
            <dl class="grid grid-cols-2 gap-2 text-sm md:grid-cols-5">
                <div><dt class="text-gray-500">Shift</dt><dd class="font-medium">{{ $current->shift->code }} — {{ $current->shift->name }}</dd></div>
                <div><dt class="text-gray-500">Attendance</dt><dd>{{ $current->attendance_mode->value }}</dd></div>
                <div><dt class="text-gray-500">OT mode</dt><dd>{{ $current->ot_mode?->value ?? 'Default' }}</dd></div>
                <div><dt class="text-gray-500">Late deduction</dt><dd>{{ $current->late_deduction === null ? 'Default' : ($current->late_deduction ? 'Yes' : 'No') }}</dd></div>
                <div><dt class="text-gray-500">Break available</dt><dd>{{ $current->break_available === null ? 'Default' : ($current->break_available ? 'Yes' : 'No') }}</dd></div>
                <div class="md:col-span-5"><dt class="text-gray-500">Times</dt><dd>{{ $current->uses_custom_times ? 'Custom times' : "Use the shift's current times" }}</dd></div>
            </dl>
            @if ($current->uses_custom_times)
                <p class="mt-2 text-sm text-slate-700">
                    {{ substr((string) $current->start_time, 0, 5) }}–{{ substr((string) $current->end_time, 0, 5) }},
                    late from {{ substr((string) $current->late_from, 0, 5) }},
                    morning cut-off {{ substr((string) $current->morning_cutoff, 0, 5) }},
                    half day from {{ substr((string) $current->half_day_from, 0, 5) }},
                    early leave from {{ substr((string) $current->early_leave_from, 0, 5) }}.
                    Breaks: {{ $current->breaks->pluck('name')->join(', ') ?: 'none' }}.
                </p>
            @endif
        @else
            <p class="text-sm text-gray-500">No time profile on today.</p>
        @endif
    </div>

    <h4 class="mb-2 text-xs font-semibold uppercase text-gray-500">History</h4>
    <div class="overflow-hidden rounded border">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">From</th>
                    <th class="px-3 py-2">To</th>
                    <th class="px-3 py-2">Shift</th>
                    <th class="px-3 py-2">Times</th>
                    <th class="px-3 py-2">Attendance</th>
                    <th class="px-3 py-2">OT</th>
                    <th class="px-3 py-2">Late deduction</th>
                    <th class="px-3 py-2">Break available</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($history as $row)
                    <tr class="border-t">
                        <td class="px-3 py-2">{{ $row->effective_from->format('Y-m-d') }}</td>
                        <td class="px-3 py-2">{{ $row->effective_to?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $row->shift->code }}</td>
                        <td class="px-3 py-2">{{ $row->uses_custom_times ? 'Custom times' : 'Shift' }}</td>
                        <td class="px-3 py-2">{{ $row->attendance_mode->value }}</td>
                        <td class="px-3 py-2">{{ $row->ot_mode?->value ?? 'Default' }}</td>
                        <td class="px-3 py-2">{{ $row->late_deduction === null ? 'Default' : ($row->late_deduction ? 'Yes' : 'No') }}</td>
                        <td class="px-3 py-2">{{ $row->break_available === null ? 'Default' : ($row->break_available ? 'Yes' : 'No') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-3 py-4 text-center text-slate-500">No history.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-4 shadow-xl">
                <h2 class="text-base font-semibold text-slate-800">Change time profile</h2>
                @if ($categoryTimeStatus !== '')
                    <p class="mt-2 text-sm text-gray-600">{{ $categoryTimeStatus }}</p>
                @endif
                @if ($error !== '')
                    <p class="mt-2 text-sm text-red-600">{{ $error }}</p>
                @endif
                <div class="mt-3 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Pay month</label>
                        <select wire:model.live="pay_period_id" class="w-full rounded border px-3 py-2 text-sm">
                            <option value="">Choose a month to start on the 1st</option>
                            @foreach ($payPeriods as $period)
                                <option value="{{ $period->id }}">{{ $period->start_date->format('F Y') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Effective from</label>
                        <input type="date" wire:model="effective_from" class="w-full rounded border px-3 py-2 text-sm">
                        @error('effective_from') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Shift</label>
                        <select wire:model.live="shift_id" class="w-full rounded border px-3 py-2 text-sm">
                            <option value="">Select</option>
                            @foreach ($shifts as $shift)
                                <option value="{{ $shift->id }}">{{ $shift->code }} — {{ $shift->name }}</option>
                            @endforeach
                        </select>
                        @error('shift_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Attendance mode</label>
                        <select wire:model="attendance_mode" class="w-full rounded border px-3 py-2 text-sm">
                            @foreach ($attendanceModes as $mode)
                                <option value="{{ $mode->value }}">{{ $mode->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">OT mode</label>
                        <select wire:model="ot_mode" class="w-full rounded border px-3 py-2 text-sm">
                            <option value="DEFAULT">Default</option>
                            @foreach ($otModes as $mode)
                                <option value="{{ $mode->value }}">{{ $mode->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Late deduction</label>
                        <select wire:model="late_deduction" class="w-full rounded border px-3 py-2 text-sm">
                            <option value="DEFAULT">Default</option>
                            <option value="YES">Yes</option>
                            <option value="NO">No</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Break available</label>
                        <select wire:model="break_available" class="w-full rounded border px-3 py-2 text-sm">
                            <option value="DEFAULT">Default</option>
                            <option value="YES">Yes</option>
                            <option value="NO">No</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model.live="use_custom_times" class="rounded border">
                        Custom times for this profile
                    </label>
                    <p class="text-xs text-slate-500">Leave this off to use the shift's current times. Turn it on to keep these clock times until the next change.</p>
                    @if ($use_custom_times)
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
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
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-700">Breaks</h3>
                                <button type="button" wire:click="addBreakRow" class="text-sm text-[var(--color-erp-accent)] hover:underline">Add break</button>
                            </div>
                            @foreach ($breaks as $index => $break)
                                <div class="mb-2 grid grid-cols-1 gap-2 rounded border p-2 md:grid-cols-4" wire:key="profile-break-{{ $index }}">
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
                    @endif
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="$set('showForm', false)" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                    <button type="button" wire:click="save" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
