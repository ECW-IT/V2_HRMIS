<div class="mx-auto max-w-6xl px-6 py-8">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Payroll readiness</h1>
            <p class="text-sm text-slate-500">Active employees with missing or overdue data as of the date below.</p>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500" for="asOf">As of</label>
            <input id="asOf" type="date" wire:model.live="asOf" class="rounded border border-slate-300 px-3 py-1.5 text-sm">
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div @class([
            'rounded-xl border bg-white p-4 shadow-sm',
            'border-red-300' => $totalBlockers > 0,
            'border-slate-200' => $totalBlockers === 0,
        ])>
            <p class="text-sm font-semibold text-slate-600">Blockers</p>
            <p @class(['mt-2 text-3xl font-bold', 'text-red-600' => $totalBlockers > 0, 'text-slate-800' => $totalBlockers === 0])>
                {{ $totalBlockers }}
            </p>
        </div>
        <div @class([
            'rounded-xl border bg-white p-4 shadow-sm',
            'border-amber-300' => $totalWarnings > 0,
            'border-slate-200' => $totalWarnings === 0,
        ])>
            <p class="text-sm font-semibold text-slate-600">Warnings</p>
            <p @class(['mt-2 text-3xl font-bold', 'text-amber-600' => $totalWarnings > 0, 'text-slate-800' => $totalWarnings === 0])>
                {{ $totalWarnings }}
            </p>
        </div>
    </div>

    @foreach ($sections as $section)
        <div class="mb-8">
            @if (count($sections) > 1)
                <h2 class="mb-2 text-sm font-semibold text-slate-700">{{ $section['company']->name }}</h2>
            @endif

            <div class="overflow-hidden rounded border bg-white">
                <table class="table-compact min-w-full text-left">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Code</th>
                            <th class="px-3 py-2">Title</th>
                            <th class="px-3 py-2">Severity</th>
                            <th class="px-3 py-2">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($section['summary'] as $code => $row)
                            <tr
                                wire:click="show('{{ $code }}')"
                                class="cursor-pointer border-t hover:bg-[var(--color-erp-row-hover)]"
                            >
                                <td class="px-3 py-2 font-medium">{{ $code }}</td>
                                <td class="px-3 py-2">{{ $row['title'] }}</td>
                                <td class="px-3 py-2">
                                    {{ $row['severity'] instanceof \Modules\CoreHr\Readiness\Severity ? $row['severity']->value : $row['severity'] }}
                                </td>
                                <td class="px-3 py-2">
                                    @if ($row['count'] === 0)
                                        <span class="text-emerald-600">✓</span>
                                    @else
                                        {{ $row['count'] }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    @if ($selectedCode)
        <div class="rounded border bg-white p-4 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-slate-800">Failing employees — {{ $selectedCode }}</h2>
                <div class="flex gap-2">
                    <button type="button" wire:click="export" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">
                        Export Excel
                    </button>
                    <button type="button" wire:click="clearSelection" class="rounded border px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">
                        Close
                    </button>
                </div>
            </div>
            <table class="table-compact min-w-full text-left">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Employee no.</th>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Department</th>
                        <th class="px-3 py-2">Job</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($failingEmployees as $row)
                        <tr class="border-t">
                            <td class="px-3 py-2 font-medium">
                                @if (\Illuminate\Support\Facades\Route::has('employees.profile'))
                                    <a href="{{ route('employees.profile', $row['employee_no']) }}" class="text-[var(--color-erp-accent)] hover:underline">{{ $row['employee_no'] }}</a>
                                @else
                                    {{ $row['employee_no'] }}
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $row['name'] }}</td>
                            <td class="px-3 py-2">{{ $row['department'] }}</td>
                            <td class="px-3 py-2">{{ $row['job'] }}</td>
                        </tr>
                    @empty
                        <tr class="border-t">
                            <td colspan="4" class="px-3 py-4 text-sm text-slate-500">No employees fail this check.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
