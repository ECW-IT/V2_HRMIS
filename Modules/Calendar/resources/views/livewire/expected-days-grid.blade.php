<div class="p-4">
    <div class="mb-4">
        <h1 class="text-lg font-semibold text-slate-800">Expected working days</h1>
        <p class="text-sm text-slate-500">Days each grade must work in a pay period. Entered, not derived from the calendar.</p>
    </div>

    @if ($companyId === null)
        <div class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            Select a company first (use the company dropdown in the top bar).
        </div>
    @else
        @if ($message !== '')
            <div class="mb-3 rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ $message }}</div>
        @endif
        @if ($error !== '')
            <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $error }}</div>
        @endif

        @can('period.manage')
            <div class="mb-4 flex flex-wrap items-end gap-3 rounded border bg-white p-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Monthly late grace (minutes)</label>
                    <input type="number" wire:model="monthly_late_grace_minutes" min="0" class="w-32 rounded border px-3 py-2 text-sm">
                    @error('monthly_late_grace_minutes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="button" wire:click="saveLateGrace" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">
                    Save late grace
                </button>
            </div>
        @else
            <div class="mb-4 rounded border bg-white px-3 py-2 text-sm text-slate-700">
                Monthly late grace: {{ $monthly_late_grace_minutes }} minutes
            </div>
        @endcan

        <div class="overflow-x-auto rounded border bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="sticky left-0 bg-slate-50 px-3 py-2">Grade</th>
                        @foreach ($periodColumns as $period)
                            <th class="px-3 py-2 text-center">
                                <div>{{ $period->start_date->format('M Y') }}</div>
                                <div class="mt-0.5 font-normal normal-case text-slate-400">{{ $period->status->value }}</div>
                                @can('period.manage')
                                    @if ($period->status->value === 'OPEN')
                                        <button type="button" wire:click="copyFromPrevious({{ $period->id }})" class="mt-1 text-[11px] font-medium normal-case text-[var(--color-erp-accent)] hover:underline">
                                            Copy from previous month
                                        </button>
                                    @endif
                                @endcan
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($grades as $grade)
                        <tr class="border-t" wire:key="grade-{{ $grade->id }}">
                            <td class="sticky left-0 bg-white px-3 py-2 font-medium">{{ $grade->name }}</td>
                            @foreach ($periodColumns as $period)
                                @php
                                    $key = $period->id.':'.$grade->id;
                                    $value = $cells[$key] ?? null;
                                    $empty = $value === null || $value === '';
                                    $open = $period->status->value === 'OPEN';
                                @endphp
                                <td @class([
                                    'px-3 py-2 text-center',
                                    'bg-amber-50' => $open && $empty,
                                ])>
                                    @can('period.manage')
                                        @if ($open)
                                            <div class="flex items-center justify-center gap-1">
                                                <input
                                                    type="number"
                                                    step="0.5"
                                                    min="0"
                                                    wire:model="cells.{{ $key }}"
                                                    class="w-20 rounded border px-2 py-1 text-sm"
                                                >
                                                <button type="button" wire:click="saveCell({{ $period->id }}, {{ $grade->id }})" class="text-xs text-[var(--color-erp-accent)] hover:underline">
                                                    Save
                                                </button>
                                            </div>
                                        @else
                                            <span class="text-slate-600">{{ $value ?? '—' }}</span>
                                        @endif
                                    @else
                                        <span class="text-slate-600">{{ $value ?? '—' }}</span>
                                    @endcan
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 1 + $periodColumns->count() }}" class="px-3 py-6 text-center text-slate-500">No active grades.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
