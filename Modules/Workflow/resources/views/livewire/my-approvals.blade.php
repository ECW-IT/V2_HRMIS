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

    @php($rows = $tab === 'waiting' ? $waiting : $mine)

    <div class="overflow-hidden rounded border bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Request no.</th>
                    <th class="px-3 py-2">Type</th>
                    <th class="px-3 py-2">Company</th>
                    <th class="px-3 py-2">Requested by</th>
                    <th class="px-3 py-2">Requested at</th>
                    <th class="px-3 py-2">Summary</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $request)
                    <tr class="border-t">
                        <td class="px-3 py-2 font-medium">#{{ $request->id }}</td>
                        <td class="px-3 py-2">{{ $request->definition->name }}</td>
                        <td class="px-3 py-2">{{ $request->company?->code }}</td>
                        <td class="px-3 py-2">{{ $request->requester?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $request->requested_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2">{{ $request->action->value }} {{ class_basename($request->subject_type) }}</td>
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
                        <td colspan="7" class="px-3 py-6 text-center text-slate-500">No requests.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($selected)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-2xl rounded-lg bg-white p-4 shadow-xl">
                <h2 class="text-base font-semibold text-slate-800">Request #{{ $selected->id }} — {{ $selected->definition->name }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $selected->company?->name }} · by {{ $selected->requester?->name }}
                </p>

                <div class="mt-3 overflow-hidden rounded border">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Field</th>
                                <th class="px-3 py-2 text-left">Current</th>
                                <th class="px-3 py-2 text-left">Requested</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (($selected->payload ?? []) as $key => $value)
                                @php($changed = ($currentValues[$key] ?? null) != $value)
                                <tr @class(['border-t', 'bg-amber-50' => $changed])>
                                    <td class="px-3 py-2 font-medium">{{ $key }}</td>
                                    <td class="px-3 py-2">{{ $currentValues[$key] ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ is_scalar($value) || $value === null ? $value : json_encode($value) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
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
