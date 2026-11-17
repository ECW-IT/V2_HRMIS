<div class="p-4">
    @if ($notice)
        <div class="mb-3 rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ $notice }}</div>
    @endif
    @if ($formError && ! $showCompensationForm && ! $showComponentForm)
        <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Compensation</h1>
            <p class="text-sm text-slate-500">Current pay for one employee. A change is requested, and applied when you may do that directly.</p>
        </div>
        @if ($canManage)
            <div class="flex gap-2">
                <button type="button" wire:click="openCompensation" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">New compensation</button>
                <button type="button" wire:click="openComponent" class="rounded border px-3 py-1.5 text-sm">Add component</button>
            </div>
        @endif
    </div>

    <div class="mb-4 max-w-md">
        <label class="mb-1 block text-xs font-medium text-gray-600">Employee</label>
        <select wire:model.live="employmentId" class="w-full rounded border px-2 py-1.5 text-sm">
            <option value="">Choose an employee</option>
            @foreach ($employments as $row)
                <option value="{{ $row->id }}">{{ $row->employee_no }} — {{ $row->person?->first_name }} {{ $row->person?->last_name }}</option>
            @endforeach
        </select>
    </div>

    @if ($employment)
        <div class="mb-4 rounded bg-white p-4 shadow">
            <h2 class="mb-2 text-sm font-semibold text-slate-700">Current</h2>
            @if ($current)
                <p class="text-sm text-slate-700">Basic {{ $current->basic_salary }} · {{ $current->ot_rate_mode->value }} · from {{ $current->effective_from->toDateString() }}</p>
            @else
                <p class="text-sm text-slate-500">No compensation is in force today.</p>
            @endif
        </div>

        <div class="mb-4 overflow-hidden rounded bg-white shadow">
            <div class="border-b bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">History</div>
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">From</th>
                        <th class="px-3 py-2">Basic</th>
                        <th class="px-3 py-2">OT mode</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($history as $row)
                        <tr class="border-t">
                            <td class="px-3 py-2">{{ $row->effective_from->toDateString() }}</td>
                            <td class="px-3 py-2 font-medium">{{ $row->basic_salary }}</td>
                            <td class="px-3 py-2">{{ $row->ot_rate_mode->value }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-3 py-6 text-center text-slate-500">No compensation yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="overflow-hidden rounded bg-white shadow">
            <div class="border-b bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">Components</div>
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Code</th>
                        <th class="px-3 py-2">Amount</th>
                        <th class="px-3 py-2">From</th>
                        <th class="px-3 py-2">To</th>
                        @if ($canManage)
                            <th class="px-3 py-2"></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($components as $row)
                        <tr class="border-t">
                            <td class="px-3 py-2 font-medium">{{ $row->payComponent?->code }}</td>
                            <td class="px-3 py-2">{{ $row->amount }}</td>
                            <td class="px-3 py-2">{{ $row->effective_from->toDateString() }}</td>
                            <td class="px-3 py-2">{{ $row->effective_to?->toDateString() ?? 'Open' }}</td>
                            @if ($canManage)
                                <td class="px-3 py-2 text-right">
                                    @if ($row->effective_to === null)
                                        <button type="button" wire:click="endComponent({{ $row->id }})" class="text-sm text-slate-700">End</button>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">No components yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($canManage)
                <div class="border-t px-3 py-3">
                    <label class="mb-1 block text-xs font-medium text-gray-600">End on</label>
                    <input type="date" wire:model="end_on" class="rounded border px-2 py-1.5 text-sm">
                </div>
            @endif
        </div>
    @endif

    @if ($showCompensationForm)
        <div class="mt-4 rounded bg-white p-4 shadow">
            @if ($formError)
                <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
            @endif
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Effective from</label>
                    <input type="date" wire:model="from" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Basic salary</label>
                    <input type="text" wire:model="basic_salary" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">OT rate mode</label>
                    <select wire:model="ot_rate_mode" class="w-full rounded border px-3 py-2 text-sm">
                        @foreach ($modes as $mode)
                            <option value="{{ $mode->value }}">{{ $mode->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Fixed OT rate</label>
                    <input type="text" wire:model="ot_rate" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Attendance tier</label>
                    <select wire:model="attendance_tier_code" class="w-full rounded border px-3 py-2 text-sm">
                        <option value="">None</option>
                        @foreach ($tiers as $code)
                            <option value="{{ $code }}">{{ $code }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Reason</label>
                    <input type="text" wire:model="change_reason" class="w-full rounded border px-3 py-2 text-sm">
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                <button type="button" wire:click="saveCompensation" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
            </div>
        </div>
    @endif

    @if ($showComponentForm)
        <div class="mt-4 rounded bg-white p-4 shadow">
            @if ($formError)
                <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
            @endif
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Component</label>
                    <select wire:model="component_code" class="w-full rounded border px-3 py-2 text-sm">
                        <option value="">Choose a component</option>
                        @foreach ($payComponents as $component)
                            <option value="{{ $component->code }}">{{ $component->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Amount</label>
                    <input type="text" wire:model="component_amount" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">From</label>
                    <input type="date" wire:model="component_from" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">To</label>
                    <input type="date" wire:model="component_to" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-gray-600">Note</label>
                    <input type="text" wire:model="component_note" class="w-full rounded border px-3 py-2 text-sm">
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                <button type="button" wire:click="saveComponent" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
            </div>
        </div>
    @endif
</div>
