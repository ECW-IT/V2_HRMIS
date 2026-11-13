<div class="p-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Employee categories</h1>
            <p class="text-sm text-slate-500">Shift, leave and OT profiles used on employments.</p>
        </div>
        @can('org.manage')
            <button type="button" wire:click="openCreate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">
                New category
            </button>
        @endcan
    </div>

    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search categories" class="mb-3 w-full max-w-sm rounded border border-slate-300 px-3 py-2 text-sm">

    <div class="overflow-hidden rounded border bg-white">
        <table class="table-compact min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Code</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr class="border-t">
                        <td class="px-3 py-2 font-mono text-xs font-medium">{{ $category->code }}</td>
                        <td class="px-3 py-2">{{ $category->name }}</td>
                        <td class="px-3 py-2">{{ $category->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="space-x-2 whitespace-nowrap px-3 py-2 text-right">
                            @can('org.manage')
                                <button type="button" wire:click="openEdit({{ $category->id }})" class="text-[var(--color-erp-accent)] hover:underline">Edit</button>
                                <button type="button" wire:click="toggleActive({{ $category->id }})" class="text-slate-600 hover:underline">
                                    {{ $category->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">No categories found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white p-4 shadow-xl">
                <h2 class="text-base font-semibold text-slate-800">{{ $editingId ? 'Edit category' : 'New category' }}</h2>

                <div class="mt-3 space-y-5">
                    <div>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Identity</h3>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Code</label>
                                <input type="text" wire:model="code" class="w-full rounded border px-3 py-2 font-mono text-sm">
                                @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Name</label>
                                <input type="text" wire:model="name" class="w-full rounded border px-3 py-2 text-sm">
                                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Leave days</h3>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Annual</label>
                                <input type="number" wire:model="annual_leaves" min="0" class="w-full rounded border px-3 py-2 text-sm">
                                @error('annual_leaves') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Casual</label>
                                <input type="number" wire:model="casual_leaves" min="0" class="w-full rounded border px-3 py-2 text-sm">
                                @error('casual_leaves') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Medical</label>
                                <input type="number" wire:model="medical_leaves" min="0" class="w-full rounded border px-3 py-2 text-sm">
                                @error('medical_leaves') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Rates</h3>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Normal OT rate</label>
                                <input type="number" step="0.01" wire:model="normal_ot_rate" class="w-full rounded border px-3 py-2 text-sm">
                                @error('normal_ot_rate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Double OT rate</label>
                                <input type="number" step="0.01" wire:model="double_ot_rate" class="w-full rounded border px-3 py-2 text-sm">
                                @error('double_ot_rate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">No-pay per hour</label>
                                <input type="number" step="0.01" wire:model="no_pay_rate_per_hour" class="w-full rounded border px-3 py-2 text-sm">
                                @error('no_pay_rate_per_hour') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">No-pay per day</label>
                                <input type="number" step="0.01" wire:model="no_pay_rate_per_day" class="w-full rounded border px-3 py-2 text-sm">
                                @error('no_pay_rate_per_day') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Saturday rate</label>
                                <input type="number" step="0.01" wire:model="saturday_rate" class="w-full rounded border px-3 py-2 text-sm">
                                @error('saturday_rate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Sunday rate</label>
                                <input type="number" step="0.01" wire:model="sunday_rate" class="w-full rounded border px-3 py-2 text-sm">
                                @error('sunday_rate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Payroll and OT mode</h3>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Payroll workdays</label>
                                <input type="number" wire:model="payroll_workdays" min="0" class="w-full rounded border px-3 py-2 text-sm">
                                @error('payroll_workdays') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Payroll work hours</label>
                                <input type="number" wire:model="payroll_workhours" min="0" class="w-full rounded border px-3 py-2 text-sm">
                                @error('payroll_workhours') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Saturday OT mode</label>
                                <select wire:model="saturday_ot_mode" class="w-full rounded border px-3 py-2 text-sm">
                                    <option value="0">Custom</option>
                                    <option value="1">As act</option>
                                    <option value="2">Working day</option>
                                </select>
                                @error('saturday_ot_mode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Custom Saturday OT type</label>
                                <input type="number" step="0.01" wire:model="custom_saturday_ot_type" class="w-full rounded border px-3 py-2 text-sm">
                                @error('custom_saturday_ot_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Sunday OT mode</label>
                                <select wire:model="sunday_ot_mode" class="w-full rounded border px-3 py-2 text-sm">
                                    <option value="0">Custom</option>
                                    <option value="1">As act</option>
                                    <option value="2">Working day</option>
                                </select>
                                @error('sunday_ot_mode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Custom Sunday OT type</label>
                                <input type="number" step="0.01" wire:model="custom_sunday_ot_type" class="w-full rounded border px-3 py-2 text-sm">
                                @error('custom_sunday_ot_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Breaks</h3>
                            <button type="button" wire:click="addBreakRow" class="text-xs font-medium text-[var(--color-erp-accent)] hover:underline">Add break</button>
                        </div>
                        <div class="space-y-2">
                            @forelse ($breaks as $index => $break)
                                <div class="grid grid-cols-1 gap-2 rounded border border-slate-200 p-2 md:grid-cols-5" wire:key="break-{{ $index }}">
                                    <div class="md:col-span-2">
                                        <select wire:model="breaks.{{ $index }}.break_type_id" class="w-full rounded border px-2 py-1.5 text-sm">
                                            <option value="">Break type</option>
                                            @foreach ($breakTypes as $type)
                                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                                            @endforeach
                                        </select>
                                        @error("breaks.$index.break_type_id") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <input type="time" wire:model.live="breaks.{{ $index }}.start_time" class="w-full rounded border px-2 py-1.5 text-sm">
                                        @error("breaks.$index.start_time") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <input type="time" wire:model.live="breaks.{{ $index }}.end_time" class="w-full rounded border px-2 py-1.5 text-sm">
                                        @error("breaks.$index.end_time") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <input type="number" wire:model="breaks.{{ $index }}.duration_minutes" min="1" class="w-full rounded border px-2 py-1.5 text-sm" placeholder="Mins">
                                        <button type="button" wire:click="removeBreakRow({{ $index }})" class="pt-1 text-xs text-red-600 hover:underline">Remove</button>
                                    </div>
                                    @error("breaks.$index.duration_minutes") <p class="text-xs text-red-600 md:col-span-5">{{ $message }}</p> @enderror
                                </div>
                            @empty
                                <p class="text-sm text-slate-500">No breaks configured.</p>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Duty times</h3>
                        @if ($editingId && $dutyFieldsDirty)
                            <div class="mb-3">
                                <label class="mb-1 block text-xs font-medium text-slate-600">Duty times effective from <span class="text-red-500">*</span></label>
                                <input type="date" wire:model="duty_effective_from" class="w-full max-w-xs rounded border px-3 py-2 text-sm">
                                @error('duty_effective_from') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">On duty</label>
                                <input type="time" wire:model.live="on_duty_time" class="w-full rounded border px-3 py-2 text-sm">
                                @error('on_duty_time') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Off duty</label>
                                <input type="time" wire:model.live="off_duty_time" class="w-full rounded border px-3 py-2 text-sm">
                                @error('off_duty_time') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Morning short beginning</label>
                                <input type="time" wire:model.live="morning_short_beginning" class="w-full rounded border px-3 py-2 text-sm">
                                @error('morning_short_beginning') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Morning short ending</label>
                                <input type="time" wire:model.live="morning_short_ending" class="w-full rounded border px-3 py-2 text-sm">
                                @error('morning_short_ending') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Evening short beginning</label>
                                <input type="time" wire:model.live="evening_short_beginning" class="w-full rounded border px-3 py-2 text-sm">
                                @error('evening_short_beginning') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Evening short ending</label>
                                <input type="time" wire:model.live="evening_short_ending" class="w-full rounded border px-3 py-2 text-sm">
                                @error('evening_short_ending') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Half day beginning</label>
                                <input type="time" wire:model.live="halfday_beginning" class="w-full rounded border px-3 py-2 text-sm">
                                @error('halfday_beginning') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Late on</label>
                                <input type="time" wire:model.live="late_on" class="w-full rounded border px-3 py-2 text-sm">
                                @error('late_on') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    @if ($editingId && $dutyHistory->isNotEmpty())
                        <div>
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Duty schedule history</h3>
                            <div class="overflow-hidden rounded border">
                                <table class="table-compact min-w-full text-left text-sm">
                                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                        <tr>
                                            <th class="px-3 py-2">From</th>
                                            <th class="px-3 py-2">To</th>
                                            <th class="px-3 py-2">On duty</th>
                                            <th class="px-3 py-2">Off duty</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($dutyHistory as $schedule)
                                            <tr class="border-t">
                                                <td class="px-3 py-2">{{ $schedule->effective_from->format('Y-m-d') }}</td>
                                                <td class="px-3 py-2">{{ $schedule->effective_to?->format('Y-m-d') ?? 'open' }}</td>
                                                <td class="px-3 py-2">{{ $schedule->on_duty_time ? \Illuminate\Support\Str::of($schedule->on_duty_time)->substr(0, 5) : '—' }}</td>
                                                <td class="px-3 py-2">{{ $schedule->off_duty_time ? \Illuminate\Support\Str::of($schedule->off_duty_time)->substr(0, 5) : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                    <button type="button" wire:click="save" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
