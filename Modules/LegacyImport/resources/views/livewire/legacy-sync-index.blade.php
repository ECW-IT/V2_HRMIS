<div class="mx-auto max-w-6xl px-6 py-8">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Legacy sync</h1>
            <p class="text-sm text-slate-500">Daily change list for hand-entry into the old HRM and payroll.</p>
        </div>
        <button type="button" wire:click="runNow" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">
            Run now
        </button>
    </div>

    @if ($message !== '')
        <div class="mb-4 rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
            {{ $message }}
        </div>
    @endif

    <div class="overflow-hidden rounded border bg-white">
        <table class="table-compact min-w-full text-left">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Window</th>
                    <th class="px-3 py-2">Hires</th>
                    <th class="px-3 py-2">Exits</th>
                    <th class="px-3 py-2">Changes</th>
                    <th class="px-3 py-2">Applied</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($runs as $run)
                    <tr @class(['border-t', 'bg-amber-50' => $run->applied_at === null])>
                        <td class="px-3 py-2 text-sm">
                            {{ $run->from_at?->format('Y-m-d H:i') }}
                            →
                            {{ $run->to_at?->format('Y-m-d H:i') }}
                        </td>
                        <td class="px-3 py-2">{{ $run->new_hires }}</td>
                        <td class="px-3 py-2">{{ $run->exits }}</td>
                        <td class="px-3 py-2">{{ $run->changes }}</td>
                        <td class="px-3 py-2 text-sm">
                            @if ($run->applied_at)
                                {{ $run->appliedByUser?->name ?? '—' }}
                                <span class="text-slate-500">{{ $run->applied_at->format('Y-m-d H:i') }}</span>
                            @else
                                Not applied
                            @endif
                        </td>
                        <td class="space-x-2 whitespace-nowrap px-3 py-2 text-right">
                            @if ($run->file_path !== '')
                                <button type="button" wire:click="download({{ $run->id }})" class="text-[var(--color-erp-accent)] hover:underline">
                                    Download
                                </button>
                            @endif
                            @if ($run->applied_at === null)
                                <button type="button" wire:click="markApplied({{ $run->id }})" class="text-slate-600 hover:underline">
                                    Mark as applied
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-sm text-slate-500">No sync runs yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
