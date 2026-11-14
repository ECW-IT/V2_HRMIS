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
            <dl class="grid grid-cols-2 gap-2 text-sm md:grid-cols-4">
                <div><dt class="text-gray-500">Shift</dt><dd class="font-medium">{{ $current->shift->code }} — {{ $current->shift->name }}</dd></div>
                <div><dt class="text-gray-500">Attendance</dt><dd>{{ $current->attendance_mode->value }}</dd></div>
                <div><dt class="text-gray-500">OT mode</dt><dd>{{ $current->ot_mode->value }}</dd></div>
                <div><dt class="text-gray-500">Late deduction</dt><dd>{{ $current->late_deduction ? 'Yes' : 'No' }}</dd></div>
            </dl>
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
                    <th class="px-3 py-2">Attendance</th>
                    <th class="px-3 py-2">OT</th>
                    <th class="px-3 py-2">Late deduction</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($history as $row)
                    <tr class="border-t">
                        <td class="px-3 py-2">{{ $row->effective_from->format('Y-m-d') }}</td>
                        <td class="px-3 py-2">{{ $row->effective_to?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $row->shift->code }}</td>
                        <td class="px-3 py-2">{{ $row->attendance_mode->value }}</td>
                        <td class="px-3 py-2">{{ $row->ot_mode->value }}</td>
                        <td class="px-3 py-2">{{ $row->late_deduction ? 'Yes' : 'No' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-4 text-center text-slate-500">No history.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-4 shadow-xl">
                <h2 class="text-base font-semibold text-slate-800">Change time profile</h2>
                @if ($error !== '')
                    <p class="mt-2 text-sm text-red-600">{{ $error }}</p>
                @endif
                <div class="mt-3 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Effective from</label>
                        <input type="date" wire:model="effective_from" class="w-full rounded border px-3 py-2 text-sm">
                        @error('effective_from') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Shift</label>
                        <select wire:model="shift_id" class="w-full rounded border px-3 py-2 text-sm">
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
                            @foreach ($otModes as $mode)
                                <option value="{{ $mode->value }}">{{ $mode->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="late_deduction"> Late deduction applies</label>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="$set('showForm', false)" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                    <button type="button" wire:click="save" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
