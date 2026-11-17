<div class="p-4">
    @if ($formError && ! $showForm)
        <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Loans</h1>
            <p class="text-sm text-slate-500">Request a loan. Instalments appear on the loan after it is approved.</p>
        </div>
        <button type="button" wire:click="openCreate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">New loan</button>
    </div>

    <div class="mb-4 grid gap-3 rounded bg-white p-3 shadow sm:grid-cols-4">
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Paid on</label>
            <input type="date" wire:model="paidOn" class="w-full rounded border px-2 py-1.5 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Payment reference</label>
            <input type="text" wire:model="paymentReference" class="w-full rounded border px-2 py-1.5 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Grace month</label>
            <input type="date" wire:model="graceMonth" class="w-full rounded border px-2 py-1.5 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Grace reason</label>
            <input type="text" wire:model="graceReason" class="w-full rounded border px-2 py-1.5 text-sm">
        </div>
    </div>

    <div class="space-y-3">
        @forelse ($loans as $loan)
            <div class="rounded bg-white p-3 shadow">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="font-medium text-slate-800">{{ $loan->loan_no }} — {{ $loan->loanType?->name }}</div>
                        <div class="text-sm text-slate-600">{{ $loan->employment?->employee_no }} · {{ $loan->amount }} · {{ $loan->instalments }} instalments · {{ $loan->status->value }}</div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($loan->status === $pending || $loan->status === $approved)
                            <button type="button" wire:click="cancel({{ $loan->id }})" class="rounded border px-2 py-1 text-sm">Cancel</button>
                        @endif
                        @if ($canPay && $loan->status === $approved)
                            <button type="button" wire:click="markPaid({{ $loan->id }})" class="rounded border px-2 py-1 text-sm">Mark paid</button>
                        @endif
                        @if ($loan->instalmentRows->isNotEmpty())
                            <button type="button" wire:click="grace({{ $loan->id }})" class="rounded border px-2 py-1 text-sm">Grace</button>
                        @endif
                    </div>
                </div>
                @if ($loan->instalmentRows->isNotEmpty())
                    <table class="mt-3 min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="py-1 pr-3">Due</th>
                                <th class="py-1 pr-3">Amount</th>
                                <th class="py-1">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($loan->instalmentRows as $instalment)
                                <tr class="border-t">
                                    <td class="py-1 pr-3">{{ $instalment->due_month->toDateString() }}</td>
                                    <td class="py-1 pr-3">{{ $instalment->amount }}</td>
                                    <td class="py-1">{{ $instalment->status->value }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @empty
            <div class="rounded bg-white px-3 py-6 text-center text-sm text-slate-500 shadow">No loans yet.</div>
        @endforelse
    </div>

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
                            <option value="{{ $employment->id }}">{{ $employment->employee_no }} — {{ $employment->person?->first_name }} {{ $employment->person?->last_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Loan type</label>
                    <select wire:model="loan_type_code" class="w-full rounded border px-3 py-2 text-sm">
                        <option value="">Choose a type</option>
                        @foreach ($loanTypes as $type)
                            <option value="{{ $type->code }}">{{ $type->code }} — {{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Amount</label>
                    <input type="text" wire:model="amount" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Instalments</label>
                    <input type="number" wire:model="instalments" min="1" max="60" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">First month</label>
                    <input type="date" wire:model="first_month" class="w-full rounded border px-3 py-2 text-sm">
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
