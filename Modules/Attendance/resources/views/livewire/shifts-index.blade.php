<div class="p-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Shifts</h1>
            <p class="text-sm text-slate-500">Times that decide day value, lateness and overtime.</p>
        </div>
        @can('shift.manage')
            <button type="button" wire:click="openCreate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white">
                New shift
            </button>
        @endcan
    </div>

    @if ($formError !== '')
        <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
    @endif

    <div class="overflow-x-auto rounded border bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Code</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Start–end</th>
                    <th class="px-3 py-2">Late from</th>
                    <th class="px-3 py-2">Morning cut-off</th>
                    <th class="px-3 py-2">Half day from</th>
                    <th class="px-3 py-2">Early leave from</th>
                    <th class="px-3 py-2">Breaks</th>
                    <th class="px-3 py-2">Employees now</th>
                    <th class="px-3 py-2">Active</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php($shift = $row['shift'])
                    <tr class="border-t" wire:key="shift-{{ $shift->id }}">
                        <td class="px-3 py-2 font-medium">{{ $shift->code }}</td>
                        <td class="px-3 py-2">{{ $shift->name }}</td>
                        <td class="px-3 py-2">{{ substr((string) $shift->start_time, 0, 5) }}–{{ substr((string) $shift->end_time, 0, 5) }}</td>
                        <td class="px-3 py-2">{{ substr((string) $shift->late_from, 0, 5) }}</td>
                        <td class="px-3 py-2">{{ substr((string) $shift->morning_cutoff, 0, 5) }}</td>
                        <td class="px-3 py-2">{{ substr((string) $shift->half_day_from, 0, 5) }}</td>
                        <td class="px-3 py-2">{{ substr((string) $shift->early_leave_from, 0, 5) }}</td>
                        <td class="px-3 py-2">{{ $shift->breaks->pluck('name')->join(', ') ?: '—' }}</td>
                        <td class="px-3 py-2">{{ $row['employees_now'] }}</td>
                        <td class="px-3 py-2">{{ $shift->is_active ? 'Yes' : 'No' }}</td>
                        <td class="space-x-2 whitespace-nowrap px-3 py-2 text-right">
                            @can('shift.manage')
                                <button type="button" wire:click="openEdit({{ $shift->id }})" class="text-[var(--color-erp-accent)] hover:underline">Edit</button>
                                @if ($shift->is_active)
                                    <button type="button" wire:click="deactivate({{ $shift->id }})" class="text-red-600 hover:underline">Deactivate</button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="px-3 py-6 text-center text-slate-500">No shifts yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-4 shadow-xl">
                <h2 class="text-base font-semibold text-slate-800">{{ $editingId ? 'Edit shift' : 'New shift' }}</h2>
                @if ($formError !== '')
                    <p class="mt-2 text-sm text-red-600">{{ $formError }}</p>
                @endif
                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Code</label>
                        <input type="text" wire:model="code" class="w-full rounded border px-3 py-2 text-sm">
                        @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Name</label>
                        <input type="text" wire:model="name" class="w-full rounded border px-3 py-2 text-sm">
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    @foreach ([
                        'start_time' => 'Start',
                        'end_time' => 'End',
                        'late_from' => 'Late from',
                        'morning_cutoff' => 'Morning cut-off',
                        'half_day_from' => 'Half day from',
                        'early_leave_from' => 'Early leave from',
                    ] as $field => $label)
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">{{ $label }}</label>
                            <input type="time" wire:model.live="{{ $field }}" class="w-full rounded border px-3 py-2 text-sm">
                            @error($field) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-700">Breaks</h3>
                        <button type="button" wire:click="addBreakRow" class="text-sm text-[var(--color-erp-accent)] hover:underline">Add break</button>
                    </div>
                    @foreach ($breaks as $index => $break)
                        <div class="mb-2 grid grid-cols-1 gap-2 rounded border p-2 md:grid-cols-4" wire:key="break-{{ $index }}">
                            <input type="text" wire:model="breaks.{{ $index }}.name" placeholder="Name" class="rounded border px-2 py-1 text-sm">
                            <input type="time" wire:model="breaks.{{ $index }}.start_time" class="rounded border px-2 py-1 text-sm">
                            <input type="time" wire:model="breaks.{{ $index }}.end_time" class="rounded border px-2 py-1 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" wire:model="breaks.{{ $index }}.is_paid"> Paid</label>
                                <button type="button" wire:click="removeBreakRow({{ $index }})" class="text-xs text-red-600">Remove</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    {{ $preview }}
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                    <button type="button" wire:click="save" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
