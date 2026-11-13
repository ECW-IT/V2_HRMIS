<div>
    <table class="table-compact min-w-full text-left">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-3 py-2">Date</th>
                <th class="px-3 py-2">Time</th>
                <th class="px-3 py-2">Device</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($punches as $punch)
                <tr class="border-t">
                    <td class="px-3 py-2">{{ $punch->punched_at->format('Y-m-d') }}</td>
                    <td class="px-3 py-2">{{ $punch->punched_at->format('H:i:s') }}</td>
                    <td class="px-3 py-2">{{ $punch->device?->name }}</td>
                </tr>
            @empty
                <tr class="border-t">
                    <td colspan="3" class="px-3 py-4 text-sm text-slate-500">No punches this month.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
