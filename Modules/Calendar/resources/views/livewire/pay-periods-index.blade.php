<div class="p-4">
    <div class="mb-4">
        <h1 class="text-lg font-semibold text-slate-800">Pay periods</h1>
        <p class="text-sm text-slate-500">Calendar months for the selected company. Close and reopen from the period close screen.</p>
    </div>

    @if ($companyId === null)
        <div class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            Select a company first (use the company dropdown in the top bar).
        </div>
    @else
        <div class="overflow-hidden rounded border bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Month</th>
                        <th class="px-3 py-2">Dates</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Closed</th>
                        <th class="px-3 py-2">Version</th>
                        <th class="px-3 py-2">Expected days</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php($period = $row['period'])
                        <tr class="border-t" wire:key="period-{{ $period->id }}">
                            <td class="px-3 py-2 font-medium">
                                <a href="{{ route('calendar.expected-days.index') }}" class="text-[var(--color-erp-accent)] hover:underline">
                                    {{ $period->start_date->format('F Y') }}
                                </a>
                            </td>
                            <td class="px-3 py-2">{{ $period->start_date->format('Y-m-d') }} – {{ $period->end_date->format('Y-m-d') }}</td>
                            <td class="px-3 py-2">
                                <span @class([
                                    'inline-flex rounded px-2 py-0.5 text-xs font-medium',
                                    'bg-emerald-50 text-emerald-700' => $period->status->value === 'OPEN',
                                    'bg-slate-100 text-slate-700' => $period->status->value === 'CLOSED',
                                ])>
                                    {{ $period->status->value }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-slate-600">
                                @if ($period->closed_at)
                                    {{ $period->closed_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                    @if ($period->closedBy)
                                        · {{ $period->closedBy->name }}
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $period->close_version }}</td>
                            <td @class(['px-3 py-2', 'font-medium text-red-600' => $row['missing'], 'text-slate-700' => ! $row['missing']])>
                                Expected days: {{ $row['set_count'] }} of {{ $row['grade_count'] }} set
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-6 text-center text-slate-500">No pay periods yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
