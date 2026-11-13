<div class="space-y-4">
    <div class="rounded bg-white shadow">
        <div class="border-b px-5 py-3">
            <h3 class="text-sm font-semibold text-gray-700">Timeline</h3>
        </div>
        <ol class="divide-y">
            @forelse($timeline as $item)
                <li class="flex flex-wrap items-baseline gap-x-4 gap-y-1 px-5 py-2.5">
                    <span class="w-24 shrink-0 font-mono text-xs text-gray-500">{{ $item['date'] }}</span>
                    <span class="min-w-0 flex-1 text-sm text-gray-700">
                        <span class="font-medium">{{ $item['title'] }}</span>
                        @if($item['detail'] !== '')
                            — {{ $item['detail'] }}
                        @endif
                    </span>
                    <span class="text-xs text-gray-500">{{ $item['who'] }}</span>
                    @if($item['request'])
                        <span class="font-mono text-xs text-gray-500">Request #{{ $item['request'] }}</span>
                    @endif
                </li>
            @empty
                <li class="px-5 py-6 text-center text-sm text-gray-500">Nothing has happened yet.</li>
            @endforelse
        </ol>
    </div>

    <div class="overflow-x-auto rounded bg-white shadow">
        <div class="border-b px-5 py-3">
            <h3 class="text-sm font-semibold text-gray-700">Assignments</h3>
        </div>
        <table class="w-full table-compact text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">From</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">To</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Department / section</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Job</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Grade</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Location</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Reports to</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($assignments as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5 text-xs text-gray-600">{{ $row->effective_from->format('Y-m-d') }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-600">{{ $row->effective_to?->format('Y-m-d') ?? 'Present' }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-600">
                            @if($row->orgUnit?->parent)
                                {{ $row->orgUnit->parent->name }} / {{ $row->orgUnit->name }}
                            @else
                                {{ $row->orgUnit?->name ?? '—' }}
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-xs text-gray-600">{{ $row->job?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-600">{{ $row->grade?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-600">{{ $row->location?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-600">{{ $row->manager?->person?->name_with_initials ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center text-sm text-gray-500">No assignments.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
