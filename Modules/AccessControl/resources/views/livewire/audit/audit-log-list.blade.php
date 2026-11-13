<div class="p-4">
    <div class="mb-4">
        <h1 class="text-lg font-semibold text-slate-800">Audit log</h1>
        <p class="text-sm text-slate-500">Read-only history of creates, updates and deletes.</p>
    </div>

    <div class="mb-4 grid grid-cols-1 gap-3 rounded border bg-white p-3 md:grid-cols-5">
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">From</label>
            <input type="date" wire:model.live="dateFrom" class="w-full rounded border px-2 py-1.5 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">To</label>
            <input type="date" wire:model.live="dateTo" class="w-full rounded border px-2 py-1.5 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">User</label>
            <select wire:model.live="userId" class="w-full rounded border px-2 py-1.5 text-sm">
                <option value="">All users</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Model</label>
            <select wire:model.live="auditableType" class="w-full rounded border px-2 py-1.5 text-sm">
                <option value="">All models</option>
                @foreach ($types as $type)
                    <option value="{{ $type }}">{{ class_basename($type) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Event</label>
            <select wire:model.live="event" class="w-full rounded border px-2 py-1.5 text-sm">
                <option value="">All events</option>
                <option value="CREATED">CREATED</option>
                <option value="UPDATED">UPDATED</option>
                <option value="DELETED">DELETED</option>
            </select>
        </div>
    </div>

    <div class="space-y-3">
        @forelse ($logs as $log)
            <div class="rounded border bg-white p-3 shadow-sm">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <div class="font-medium text-slate-800">
                        {{ $log->event }}
                        <span class="font-normal text-slate-500">· {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</span>
                    </div>
                    <div class="text-xs text-slate-500">
                        {{ $log->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}
                        · {{ $log->user?->name ?? 'System' }}
                        @if ($log->reason)
                            · {{ $log->reason }}
                        @endif
                    </div>
                </div>

                @php
                    $keys = collect(array_unique(array_merge(
                        array_keys($log->old_values ?? []),
                        array_keys($log->new_values ?? []),
                    )))->sort()->values();
                @endphp

                @if ($keys->isEmpty())
                    <p class="text-xs text-slate-400">No field changes recorded.</p>
                @else
                    <table class="min-w-full text-left text-xs">
                        <thead class="text-slate-500">
                            <tr>
                                <th class="py-1 pr-3 font-medium">Field</th>
                                <th class="py-1 pr-3 font-medium">Old</th>
                                <th class="py-1 font-medium">New</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($keys as $key)
                                <tr class="border-t border-slate-100">
                                    <td class="py-1 pr-3 font-mono text-slate-700">{{ $key }}</td>
                                    <td class="py-1 pr-3 text-slate-500">{{ $log->old_values[$key] ?? '—' }}</td>
                                    <td class="py-1 text-slate-800">{{ $log->new_values[$key] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @empty
            <div class="rounded border bg-white px-3 py-8 text-center text-sm text-slate-500">
                No audit rows match these filters.
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
</div>
