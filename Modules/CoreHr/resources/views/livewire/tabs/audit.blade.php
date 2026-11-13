<div>
    <div class="space-y-3">
        @forelse($logs as $log)
            <div class="rounded bg-white p-3 shadow">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <div class="font-medium text-gray-800">
                        {{ $log->event }}
                        <span class="font-normal text-gray-500">· {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</span>
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ $log->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}
                        · {{ $log->user?->name ?? 'System' }}
                        @if($log->reason)
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

                @if($keys->isEmpty())
                    <p class="text-xs text-gray-400">No field changes recorded.</p>
                @else
                    <table class="min-w-full text-left text-xs">
                        <thead class="text-gray-500">
                            <tr>
                                <th class="py-1 pr-3 font-medium">Field</th>
                                <th class="py-1 pr-3 font-medium">Old</th>
                                <th class="py-1 font-medium">New</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($keys as $key)
                                <tr class="border-t border-gray-100">
                                    <td class="py-1 pr-3 font-mono text-gray-700">{{ $key }}</td>
                                    <td class="py-1 pr-3 text-gray-500">{{ $log->old_values[$key] ?? '—' }}</td>
                                    <td class="py-1 text-gray-800">{{ $log->new_values[$key] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @empty
            <div class="rounded bg-white px-3 py-8 text-center text-sm text-gray-500 shadow">No audit rows for this employee.</div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
</div>
