<div class="p-4">
    @if ($formError && ! $showForm)
        <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Salary advances</h1>
            <p class="text-sm text-slate-500">Request an advance against a pay period.</p>
        </div>
        <button type="button" wire:click="openCreate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">New advance</button>
    </div>

    <div class="mb-3 max-w-xs">
        <label class="mb-1 block text-xs font-medium text-gray-600">Pay period</label>
        <select wire:model.live="payPeriodId" class="w-full rounded border px-2 py-1.5 text-sm">
            <option value="">All periods</option>
            @foreach ($periods as $period)
                <option value="{{ $period->id }}">{{ $period->year }}-{{ str_pad((string) $period->month, 2, '0', STR_PAD_LEFT) }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-hidden rounded bg-white shadow">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2">Employee</th>
                    <th class="px-3 py-2">Amount</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($advances as $advance)
                    <tr class="border-t">
                        <td class="px-3 py-2">{{ $advance->employment?->employee_no }}</td>
                        <td class="px-3 py-2 font-medium">{{ $advance->amount }}</td>
                        <td class="px-3 py-2">{{ $advance->status->value }}</td>
                        <td class="px-3 py-2 text-right">
                            @if ($advance->status === $pending || $advance->status === $approved)
                                <button type="button" wire:click="cancel({{ $advance->id }})" class="text-sm text-slate-700">Cancel</button>
                            @endif
                            @if ($canPay && $advance->status === $approved)
                                <button type="button" wire:click="markPaid({{ $advance->id }})" class="ml-2 text-sm text-[var(--color-erp-accent)]">Mark paid</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">No advances yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($canPay)
        <div class="mt-3 grid max-w-xl gap-3 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Paid on</label>
                <input type="date" wire:model="paidOn" class="w-full rounded border px-2 py-1.5 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Payment reference</label>
                <input type="text" wire:model="paymentReference" class="w-full rounded border px-2 py-1.5 text-sm">
            </div>
        </div>
    @endif

    @if ($showForm)
        <div class="mt-4 rounded bg-white p-4 shadow">
            @if ($formError)
                <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
            @endif
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Employee</label>
                    <select wire:model="employmentId" class="w-full rounded border px-3 py-2 text-sm">
                        <option value="">Choose an employee</option>
                        @foreach ($employments as $employment)
                            <option value="{{ $employment->id }}">{{ $employment->employee_no }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Pay period</label>
                    <select wire:model="payPeriodId" class="w-full rounded border px-3 py-2 text-sm">
                        <option value="">Choose a period</option>
                        @foreach ($periods as $period)
                            <option value="{{ $period->id }}">{{ $period->year }}-{{ str_pad((string) $period->month, 2, '0', STR_PAD_LEFT) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Amount</label>
                    <input type="text" wire:model="amount" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Reason</label>
                    <input type="text" wire:model="reason" class="w-full rounded border px-3 py-2 text-sm">
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                <button type="button" wire:click="save" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
            </div>
        </div>
    @endif
</div>
