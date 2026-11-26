@php
    $input = 'w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500';
@endphp

<div class="mb-6 space-y-4">
    @if ($notice)
        <div class="rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ $notice }}</div>
    @endif
    @if ($formError && ! $showCompensationForm && ! $showComponentForm)
        <div class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-sm font-semibold text-gray-700">Payroll details</h3>
        @if ($canManage)
            <div class="flex gap-2">
                <button type="button" wire:click="openCompensation" class="rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700">
                    {{ $current ? 'Change compensation' : 'New compensation' }}
                </button>
                <button type="button" wire:click="openComponent" class="rounded border border-blue-600 px-3 py-1.5 text-xs font-medium text-blue-600 hover:bg-blue-50">Add component</button>
            </div>
        @endif
    </div>

    <div class="rounded border bg-white px-3 py-3">
        <h4 class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Current</h4>
        @if ($current)
            <p class="text-sm text-gray-800">
                Basic {{ $current->basic_salary }} · {{ $current->ot_rate_mode->value }}
                @if ($current->ot_rate_mode->value === 'FIXED' && $current->ot_rate !== null)
                    · OT rate {{ $current->ot_rate }}
                @endif
                · from {{ $current->effective_from->toDateString() }}
            </p>
        @else
            <p class="text-sm text-gray-500">No compensation is in force today.</p>
        @endif
    </div>

    <div class="overflow-hidden rounded border bg-white">
        <div class="border-b bg-gray-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">History</div>
        <table class="min-w-full text-sm">
            <thead class="text-left text-xs text-gray-500">
                <tr>
                    <th class="px-3 py-2">From</th>
                    <th class="px-3 py-2">Basic</th>
                    <th class="px-3 py-2">OT mode</th>
                    <th class="px-3 py-2">OT rate</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($history as $row)
                    <tr class="border-t">
                        <td class="px-3 py-2">{{ $row->effective_from->toDateString() }}</td>
                        <td class="px-3 py-2 font-medium">{{ $row->basic_salary }}</td>
                        <td class="px-3 py-2">{{ $row->ot_rate_mode->value }}</td>
                        <td class="px-3 py-2">
                            @if ($row->ot_rate_mode->value === 'FIXED' && $row->ot_rate !== null)
                                {{ $row->ot_rate }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-4 text-center text-gray-500">No compensation yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @foreach ([
        ['label' => 'Earnings', 'rows' => $earningComponents, 'empty' => 'No earnings yet.'],
        ['label' => 'Deductions', 'rows' => $deductionComponents, 'empty' => 'No deductions yet.'],
    ] as $section)
        <div class="overflow-hidden rounded border bg-white">
            <div class="border-b bg-gray-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $section['label'] }}</div>
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs text-gray-500">
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
                    @forelse ($section['rows'] as $row)
                        <tr class="border-t">
                            <td class="px-3 py-2 font-medium">{{ $row->payComponent?->code }}</td>
                            <td class="px-3 py-2">{{ $row->amount }}</td>
                            <td class="px-3 py-2">{{ $row->effective_from->toDateString() }}</td>
                            <td class="px-3 py-2">{{ $row->effective_to?->toDateString() ?? 'Open' }}</td>
                            @if ($canManage)
                                <td class="px-3 py-2 text-right space-x-2">
                                    @if ($row->effective_to === null)
                                        <button type="button" wire:click="openEditComponent({{ $row->id }})" class="text-xs text-blue-600 hover:underline">Edit</button>
                                        <button type="button" wire:click="endComponent({{ $row->id }})" class="text-xs text-gray-700 hover:underline">End</button>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-4 text-center text-gray-500">{{ $section['empty'] }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach
    @if ($canManage)
        <div class="rounded border bg-white px-3 py-3">
            <label class="mb-1 block text-xs font-medium text-gray-600">End on</label>
            <input type="date" wire:model="end_on" class="rounded border px-2 py-1.5 text-sm">
        </div>
    @endif

    @if ($showCompensationForm)
        <div class="rounded border bg-white p-4">
            @if ($formError)
                <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
            @endif
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Effective from</label>
                    <input type="date" wire:model="from" class="{{ $input }}">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Basic salary</label>
                    <input type="text" wire:model="basic_salary" class="{{ $input }}">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">OT rate mode</label>
                    <select wire:model.live="ot_rate_mode" class="{{ $input }}">
                        @foreach ($modes as $mode)
                            <option value="{{ $mode->value }}">{{ $mode->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Fixed OT rate</label>
                    <input type="text" wire:model="ot_rate" class="{{ $input }}" @disabled($ot_rate_mode !== 'FIXED')>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Attendance tier</label>
                    <select wire:model="attendance_tier_code" class="{{ $input }}">
                        <option value="">None</option>
                        @foreach ($tiers as $code)
                            <option value="{{ $code }}">{{ $code }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Reason</label>
                    <input type="text" wire:model="change_reason" class="{{ $input }}">
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="button" wire:click="saveCompensation" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Save</button>
            </div>
        </div>
    @endif

    @if ($showComponentForm)
        <div class="rounded border bg-white p-4">
            @if ($formError)
                <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
            @endif
            <div class="grid gap-3 sm:grid-cols-2">
                @if ($editingComponent)
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Kind</label>
                        <input type="text" value="{{ $component_kind }}" class="{{ $input }} bg-gray-50" disabled>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Component</label>
                        <input type="text" value="{{ $component_code }}" class="{{ $input }} bg-gray-50" disabled>
                    </div>
                @else
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Kind</label>
                        <select wire:model.live="component_kind" class="{{ $input }}">
                            <option value="EARNING">Earning</option>
                            <option value="DEDUCTION">Deduction</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Component</label>
                        <x-searchable-select
                            model="component_code"
                            :value="$component_code"
                            :options="$component_kind === 'DEDUCTION' ? $attachableDeductions : $attachableEarnings"
                            empty-label="Select"
                            aria-label="Pay component"
                            search-placeholder="Search component"
                            :input-class="$input"
                        />
                    </div>
                @endif
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Amount</label>
                    <input type="text" wire:model="component_amount" class="{{ $input }}">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">From</label>
                    <input type="date" wire:model="component_from" class="{{ $input }}">
                </div>
                @unless ($editingComponent)
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">To</label>
                        <input type="date" wire:model="component_to" class="{{ $input }}">
                    </div>
                @endunless
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-gray-600">Note</label>
                    <input type="text" wire:model="component_note" class="{{ $input }}">
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="button" wire:click="saveComponent" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Save</button>
            </div>
        </div>
    @endif
</div>
