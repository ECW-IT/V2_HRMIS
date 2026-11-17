<div class="p-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Company pay settings</h1>
            <p class="text-sm text-slate-500">Hours, late divisor, attendance offsets and the overtime multiplier for the selected company.</p>
        </div>
        @if ($canConfigure)
            <button type="button" wire:click="openCreate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">New settings</button>
        @endif
    </div>

    @if ($needsCompany)
        <div class="mb-3 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">Select one company.</div>
    @endif

    <div class="overflow-hidden rounded bg-white shadow">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2">From</th>
                    <th class="px-3 py-2">Hours / month</th>
                    <th class="px-3 py-2">Hours / day</th>
                    <th class="px-3 py-2">Late divisor</th>
                    <th class="px-3 py-2">Offsets</th>
                    <th class="px-3 py-2">OT</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="border-t">
                        <td class="px-3 py-2 font-medium">{{ $row->effective_from->toDateString() }}</td>
                        <td class="px-3 py-2">{{ $row->hours_per_month }}</td>
                        <td class="px-3 py-2">{{ $row->hours_per_day }}</td>
                        <td class="px-3 py-2">{{ $row->late_divisor }}</td>
                        <td class="px-3 py-2">{{ $row->attendance_min_offset }} – {{ $row->attendance_max_offset }}</td>
                        <td class="px-3 py-2">{{ $row->ot_multiplier }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">No pay settings yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="mt-4 rounded bg-white p-4 shadow">
            @if ($formError)
                <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
            @endif
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Hours per month</label>
                    <input type="number" wire:model="hours_per_month" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Hours per day</label>
                    <input type="number" wire:model="hours_per_day" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Late divisor</label>
                    <input type="number" wire:model="late_divisor" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Attendance min offset</label>
                    <input type="text" wire:model="attendance_min_offset" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Attendance max offset</label>
                    <input type="text" wire:model="attendance_max_offset" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">OT multiplier</label>
                    <input type="text" wire:model="ot_multiplier" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Effective from</label>
                    <input type="date" wire:model="effective_from" class="w-full rounded border px-3 py-2 text-sm">
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                <button type="button" wire:click="save" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
            </div>
        </div>
    @endif
</div>
