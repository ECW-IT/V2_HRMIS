<div class="p-4">
    @if ($formError && ! $showForm)
        <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Pay runs</h1>
            <p class="text-sm text-slate-500">Open a pay run for a period, then calculate, approve and lock it.</p>
        </div>
        @if ($canManage)
            <button type="button" wire:click="openCreate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">New pay run</button>
        @endif
    </div>

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="min-w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2">Period</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Employees</th>
                    <th class="px-3 py-2">Errors</th>
                    <th class="px-3 py-2">Net pay</th>
                    <th class="px-3 py-2">Calculated</th>
                    <th class="px-3 py-2">Approved</th>
                    <th class="px-3 py-2">Locked</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($runs as $run)
                    <tr class="border-t" wire:key="pay-run-{{ $run->id }}">
                        <td class="px-3 py-2">
                            <a href="{{ route('payroll.pay-runs.show', $run) }}" class="font-medium text-[var(--color-erp-accent)] underline">
                                {{ sprintf('%04d-%02d', $run->payPeriod->year, $run->payPeriod->month) }}
                            </a>
                        </td>
                        <td class="px-3 py-2">{{ $run->status->value }}</td>
                        <td class="px-3 py-2">{{ $run->employee_count }}</td>
                        <td class="px-3 py-2">{{ $run->error_count }}</td>
                        <td class="px-3 py-2">{{ $run->net_pay }}</td>
                        <td class="px-3 py-2">{{ $run->calculated_at?->timezone('Asia/Colombo')->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2">{{ $run->approved_at?->timezone('Asia/Colombo')->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2">{{ $run->locked_at?->timezone('Asia/Colombo')->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-6 text-center text-slate-500">No pay runs yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="mt-4 rounded bg-white p-4 shadow">
            @if ($formError)
                <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
            @endif
            <div class="max-w-sm">
                <label class="mb-1 block text-xs font-medium text-gray-600">Pay period</label>
                <select wire:model="payPeriodId" class="w-full rounded border px-3 py-2 text-sm">
                    <option value="">Choose a period</option>
                    @foreach ($periods as $period)
                        <option value="{{ $period->id }}">{{ sprintf('%04d-%02d', $period->year, $period->month) }} ({{ $period->status->value }})</option>
                    @endforeach
                </select>
                @error('payPeriodId')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                <button type="button" wire:click="openRun" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Open pay run</button>
            </div>
        </div>
    @endif
</div>
