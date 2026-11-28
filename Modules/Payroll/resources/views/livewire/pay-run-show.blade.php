<div class="p-4">
    @if ($formError)
        <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
    @endif
    @if ($notice)
        <div class="mb-3 rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ $notice }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('payroll.pay-runs') }}" class="text-sm text-[var(--color-erp-accent)] underline">Pay runs</a>
            <h1 class="text-lg font-semibold text-slate-800">
                Pay run {{ sprintf('%04d-%02d', $run->payPeriod->year, $run->payPeriod->month) }}
            </h1>
            <p class="text-sm text-slate-600">Status {{ $run->status->value }} · {{ $run->employee_count }} employees · {{ $run->error_count }} errors</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canManage && ($run->status === $draft || $run->status === $calculated))
                <button type="button" wire:click="calculate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">Calculate</button>
            @endif
            @if ($canApprove && $run->status === $calculated)
                <button type="button" wire:click="approve" class="rounded border px-3 py-1.5 text-sm">Approve</button>
            @endif
            @if ($canLock && $run->status === $approved)
                <button type="button" wire:click="lock" class="rounded border px-3 py-1.5 text-sm">Lock</button>
            @endif
        </div>
    </div>

    <div class="mb-4 grid gap-3 rounded bg-white p-3 shadow sm:grid-cols-3 lg:grid-cols-6">
        <div>
            <div class="text-xs text-slate-500">Sheet 1</div>
            <div class="font-medium text-slate-800">{{ $run->gross_sheet1 }}</div>
        </div>
        <div>
            <div class="text-xs text-slate-500">Sheet 2</div>
            <div class="font-medium text-slate-800">{{ $run->gross_sheet2 }}</div>
        </div>
        <div>
            <div class="text-xs text-slate-500">Deductions</div>
            <div class="font-medium text-slate-800">{{ $run->total_deductions }}</div>
        </div>
        <div>
            <div class="text-xs text-slate-500">Net pay</div>
            <div class="font-medium text-slate-800">{{ $run->net_pay }}</div>
        </div>
        <div>
            <div class="text-xs text-slate-500">Employer</div>
            <div class="font-medium text-slate-800">{{ $run->employer_total }}</div>
        </div>
        <div>
            <div class="text-xs text-slate-500">Carried forward</div>
            <div class="font-medium text-slate-800">{{ $run->carried_forward_out }}</div>
        </div>
    </div>

    @if ($canSendBack && $run->status === $approved)
        <div class="mb-4 flex flex-wrap items-end gap-2 rounded bg-white p-3 shadow">
            <div class="min-w-64 flex-1">
                <label class="mb-1 block text-xs font-medium text-gray-600">Reason for sending back</label>
                <input type="text" wire:model="sendBackReason" class="w-full rounded border px-3 py-2 text-sm">
            </div>
            <button type="button" wire:click="sendBack" class="rounded border px-3 py-2 text-sm">Send back</button>
        </div>
    @endif

    <div class="mb-4 overflow-x-auto rounded bg-white shadow">
        <table class="min-w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2">Employee</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Error</th>
                    <th class="px-3 py-2">Sheet 1</th>
                    <th class="px-3 py-2">Sheet 2</th>
                    <th class="px-3 py-2">Net</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($run->employees as $employee)
                    <tr class="border-t {{ $selectedEmployeeId === $employee->id ? 'bg-slate-50' : '' }}" wire:key="pay-run-employee-{{ $employee->id }}">
                        <td class="px-3 py-2">
                            <button type="button" wire:click="selectEmployee({{ $employee->id }})" class="font-medium text-[var(--color-erp-accent)] underline">{{ $employee->employee_no }}</button>
                        </td>
                        <td class="px-3 py-2">{{ $employee->name }}</td>
                        <td class="px-3 py-2">{{ $employee->status->value }}</td>
                        <td class="px-3 py-2">{{ $employee->error }}</td>
                        <td class="px-3 py-2">{{ $employee->gross_sheet1 }}</td>
                        <td class="px-3 py-2">{{ $employee->gross_sheet2 }}</td>
                        <td class="px-3 py-2">{{ $employee->net_pay }}</td>
                        <td class="px-3 py-2">
                            <div class="flex flex-wrap gap-2">
                                @if ($canManage && $openForChange)
                                    <button type="button" wire:click="recalculate({{ $employee->employment_id }})" class="rounded border px-2 py-1 text-xs">Recalculate</button>
                                @endif
                                @if ($canExport)
                                    <a href="{{ route('payroll.payslips.show', ['run' => $run, 'employee' => $employee]) }}" class="rounded border px-2 py-1 text-xs">Payslip</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @if ($selectedEmployeeId === $employee->id)
                        <tr class="border-t bg-slate-50" wire:key="pay-run-lines-{{ $employee->id }}">
                            <td colspan="8" class="px-3 py-2">
                                <table class="min-w-full text-sm">
                                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="py-1 pr-3">Code</th>
                                            <th class="py-1 pr-3">Kind</th>
                                            <th class="py-1 pr-3">Sheet</th>
                                            <th class="py-1 pr-3">Quantity</th>
                                            <th class="py-1 pr-3">Rate</th>
                                            <th class="py-1">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($employee->lines as $line)
                                            <tr class="border-t">
                                                <td class="py-1 pr-3">{{ $line->code }}</td>
                                                <td class="py-1 pr-3">{{ $line->kind->value }}</td>
                                                <td class="py-1 pr-3">{{ $line->sheet }}</td>
                                                <td class="py-1 pr-3">{{ $line->quantity }}</td>
                                                <td class="py-1 pr-3">{{ $line->rate }}</td>
                                                <td class="py-1">{{ $line->amount }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="py-2 text-slate-500">No lines.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-6 text-center text-slate-500">No employees in this pay run.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($canOverride && $openForChange)
        <div class="mb-4 rounded bg-white p-4 shadow">
            <h2 class="mb-3 text-sm font-semibold text-slate-800">Override</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Employee</label>
                    <select wire:model="overrideEmploymentId" class="w-full rounded border px-3 py-2 text-sm">
                        <option value="">Choose an employee</option>
                        @foreach ($run->employees as $employee)
                            <option value="{{ $employee->employment_id }}">{{ $employee->employee_no }} — {{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Component</label>
                    <select wire:model="overrideCode" class="w-full rounded border px-3 py-2 text-sm">
                        <option value="">Choose a component</option>
                        @foreach ($components as $component)
                            <option value="{{ $component->code }}">{{ $component->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Amount</label>
                    <input type="text" wire:model="overrideAmount" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Reason</label>
                    <input type="text" wire:model="overrideReason" class="w-full rounded border px-3 py-2 text-sm">
                </div>
            </div>
            <div class="mt-3 flex justify-end">
                <button type="button" wire:click="setOverride" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Set override</button>
            </div>

            @if ($run->overrides->isNotEmpty())
                <table class="mt-4 min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="py-1 pr-3">Employee</th>
                            <th class="py-1 pr-3">Component</th>
                            <th class="py-1 pr-3">Amount</th>
                            <th class="py-1 pr-3">Reason</th>
                            <th class="py-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($run->overrides as $override)
                            <tr class="border-t" wire:key="override-{{ $override->id }}">
                                <td class="py-1 pr-3">{{ $override->employment?->employee_no }}</td>
                                <td class="py-1 pr-3">{{ $override->payComponent?->code }}</td>
                                <td class="py-1 pr-3">{{ $override->amount }}</td>
                                <td class="py-1 pr-3">{{ $override->reason }}</td>
                                <td class="py-1">
                                    <button type="button" wire:click="cancelOverride({{ $override->id }})" class="rounded border px-2 py-1 text-xs">Cancel</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    <div class="rounded bg-white p-4 shadow">
        <h2 class="mb-2 text-sm font-semibold text-slate-800">Events</h2>
        @if ($run->events->isEmpty())
            <p class="text-sm text-slate-500">No events yet.</p>
        @else
            <ul class="space-y-1 text-sm text-slate-700">
                @foreach ($run->events as $event)
                    <li>
                        {{ $event->event }}
                        · {{ $event->user?->name }}
                        · {{ $event->created_at?->timezone('Asia/Colombo')->format('Y-m-d H:i') }}
                        @if ($event->note)
                            · {{ $event->note }}
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
