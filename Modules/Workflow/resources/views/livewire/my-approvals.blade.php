<div class="p-4">
    <div class="mb-4">
        <h1 class="text-lg font-semibold text-slate-800">My approvals</h1>
        <p class="text-sm text-slate-500">Review and decide pending requests.</p>
    </div>

    <div class="mb-4 flex gap-2 border-b">
        <button type="button" wire:click="$set('tab', 'waiting')" @class([
            'px-3 py-2 text-sm font-medium border-b-2 -mb-px',
            'border-[var(--color-erp-accent)] text-[var(--color-erp-accent)]' => $tab === 'waiting',
            'border-transparent text-slate-500' => $tab !== 'waiting',
        ])>
            Waiting for me ({{ $waiting->count() }})
        </button>
        <button type="button" wire:click="$set('tab', 'mine')" @class([
            'px-3 py-2 text-sm font-medium border-b-2 -mb-px',
            'border-[var(--color-erp-accent)] text-[var(--color-erp-accent)]' => $tab === 'mine',
            'border-transparent text-slate-500' => $tab !== 'mine',
        ])>
            My requests
        </button>
    </div>

    @if ($notice !== '')
        <p class="mb-3 rounded bg-slate-100 px-3 py-2 text-sm text-slate-700">{{ $notice }}</p>
    @endif

    @if ($tab === 'waiting')
        <div class="mb-3">
            <button
                type="button"
                wire:click="approveSelected"
                @disabled($selectedIds === [])
                class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white disabled:cursor-not-allowed disabled:opacity-50"
            >
                Approve selected
            </button>
        </div>
    @endif

    @php($rows = $tab === 'waiting' ? $waiting : $mine)
    @php($colspan = $tab === 'waiting' ? 9 : 8)

    <div class="overflow-hidden rounded border bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    @if ($tab === 'waiting')
                        <th class="px-3 py-2">
                            <input
                                type="checkbox"
                                aria-label="Select all"
                                wire:click="toggleAll"
                                @checked($allSelected)
                                @disabled($waiting->isEmpty())
                            />
                        </th>
                    @endif
                    <th class="px-3 py-2">Request no.</th>
                    <th class="px-3 py-2">Type</th>
                    <th class="px-3 py-2">Requested by</th>
                    <th class="px-3 py-2">Employee</th>
                    <th class="px-3 py-2">Field</th>
                    <th class="px-3 py-2">Old value</th>
                    <th class="px-3 py-2">New value</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $request)
                    @php($summary = $summaries[$request->id])
                    <tr class="border-t align-top" wire:key="approval-{{ $request->id }}">
                        @if ($tab === 'waiting')
                            <td class="px-3 py-2">
                                <input type="checkbox" wire:model="selectedIds" value="{{ $request->id }}" />
                            </td>
                        @endif
                        <td class="px-3 py-2 font-medium">#{{ $request->id }}</td>
                        <td class="px-3 py-2">{{ $request->definition->name }}</td>
                        <td class="px-3 py-2">{{ $request->requester?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $summary['employee'] }}</td>
                        @if ($summary['hidden'])
                            <td class="px-3 py-2" colspan="3">{{ $summary['hidden'] }}</td>
                        @elseif ($summary['changes'] === [])
                            <td class="px-3 py-2">—</td>
                            <td class="px-3 py-2">—</td>
                            <td class="px-3 py-2">—</td>
                        @else
                            <td class="px-3 py-2">
                                @foreach ($summary['changes'] as $change)
                                    <div class="py-0.5">{{ $change['field'] }}</div>
                                @endforeach
                            </td>
                            <td class="px-3 py-2">
                                @foreach ($summary['changes'] as $change)
                                    <div class="py-0.5 break-words">{{ $change['old'] }}</div>
                                @endforeach
                            </td>
                            <td class="px-3 py-2">
                                @foreach ($summary['changes'] as $change)
                                    <div class="py-0.5 break-words">{{ $change['new'] }}</div>
                                @endforeach
                            </td>
                        @endif
                        <td class="px-3 py-2 text-right space-x-2 whitespace-nowrap">
                            @if ($tab === 'waiting')
                                <button type="button" wire:click="open({{ $request->id }})" class="text-[var(--color-erp-accent)] hover:underline">Review</button>
                            @elseif ($request->status->value === 'PENDING')
                                <button type="button" wire:click="cancel({{ $request->id }})" class="text-slate-600 hover:underline">Cancel</button>
                            @else
                                <span class="text-xs text-slate-500">{{ $request->status->value }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $colspan }}" class="px-3 py-6 text-center text-slate-500">No requests.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($selected)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-3xl rounded-lg bg-white p-4 shadow-xl">
                <h2 class="text-base font-semibold text-slate-800">Request #{{ $selected->id }} — {{ $selected->definition->name }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $selected->company?->name }} · by {{ $selected->requester?->name }}
                </p>

                <div class="mt-3 overflow-hidden rounded border">
                    @if ($hideCompensationPayload)
                        <p class="px-3 py-4 text-sm text-slate-600">Compensation change (hidden)</p>
                    @else
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-3 py-2 text-left">Field</th>
                                    <th class="px-3 py-2 text-left">Current</th>
                                    <th class="px-3 py-2 text-left">Requested</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($reviewRows as $row)
                                    <tr @class(['border-t', 'bg-amber-50' => $row['changed']])>
                                        <td class="px-3 py-2 font-medium">{{ $row['field'] }}</td>
                                        <td class="px-3 py-2 break-words">{{ $row['current'] }}</td>
                                        <td class="px-3 py-2 break-words">{{ $row['requested'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-3 py-4 text-center text-slate-500">No change details.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    @endif
                </div>

                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-slate-600">Remarks</label>
                    <textarea wire:model="remarks" rows="2" class="w-full rounded border px-3 py-2 text-sm"></textarea>
                    @error('remarks') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="close" class="rounded border px-3 py-1.5 text-sm">Close</button>
                    <button type="button" wire:click="reject" class="rounded border border-red-300 px-3 py-1.5 text-sm text-red-700">Reject</button>
                    <button type="button" wire:click="approve" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Approve</button>
                </div>
            </div>
        </div>
    @endif
</div>
