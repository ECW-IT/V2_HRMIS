<div class="p-4">
    <div class="mb-4">
        <h1 class="text-lg font-semibold text-slate-800">HR reports</h1>
        <p class="text-sm text-slate-500">Company-scoped employee and leave reports.</p>
    </div>

    @if ($error)
        <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $error }}</div>
    @endif

    <div class="mb-4 grid grid-cols-1 gap-3 rounded bg-white p-4 shadow md:grid-cols-3">
        <div class="md:col-span-3">
            <label class="mb-1 block text-xs font-medium text-gray-600">Report</label>
            <select wire:model.live="reportType" class="w-full rounded border px-3 py-1.5 text-sm">
                @foreach ($reportTypes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @if (in_array($reportType, ['summary', 'history'], true))
            <div class="md:col-span-2">
                <label class="mb-1 block text-xs font-medium text-gray-600">Employee</label>
                <select wire:model="employmentId" class="w-full rounded border px-3 py-1.5 text-sm">
                    <option value="">— Select —</option>
                    @foreach ($employees as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if (in_array($reportType, ['resigned', 'recruitment', 'increments'], true) || ($reportType === 'leave' && $leaveMode === 'range'))
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">From</label>
                <input type="date" wire:model="dateFrom" class="w-full rounded border px-3 py-1.5 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">To</label>
                <input type="date" wire:model="dateTo" class="w-full rounded border px-3 py-1.5 text-sm">
            </div>
        @endif

        @if (in_array($reportType, ['probation', 'birthdays'], true))
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Month</label>
                <input type="month" wire:model="month" class="w-full rounded border px-3 py-1.5 text-sm">
            </div>
        @endif

        @if ($reportType === 'leave')
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Mode</label>
                <select wire:model.live="leaveMode" class="w-full rounded border px-3 py-1.5 text-sm">
                    <option value="daily">Daily</option>
                    <option value="range">Date range</option>
                </select>
            </div>
            @if ($leaveMode === 'daily')
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Date</label>
                    <input type="date" wire:model="leaveDate" class="w-full rounded border px-3 py-1.5 text-sm">
                </div>
            @endif
        @endif

        <div class="flex items-end gap-2 md:col-span-3">
            <button type="button" wire:click="runReport" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Run</button>
            <button type="button" wire:click="download" class="rounded border px-3 py-1.5 text-sm">Download Excel</button>
        </div>
    </div>

    @if ($reportTitle !== '')
        <div class="overflow-x-auto rounded bg-white shadow">
            <div class="border-b px-3 py-2 text-sm font-medium text-slate-700">{{ $reportTitle }} ({{ count($rows) }})</div>
            <table class="table-compact min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        @foreach ($headings as $heading)
                            <th class="px-3 py-2">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-t hover:bg-[var(--color-erp-row-hover)]">
                            @foreach ($row as $cell)
                                <td class="px-3 py-2">{{ $cell ?? '—' }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ max(count($headings), 1) }}" class="px-3 py-6 text-center text-slate-500">No rows.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
