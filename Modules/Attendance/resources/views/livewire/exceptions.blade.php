<div class="mx-auto max-w-[1280px] space-y-4 px-6 py-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-lg font-semibold text-gray-800">Exceptions</h1>
        <label class="text-xs text-gray-600">Open period
            <select wire:model.live="periodId" class="mt-1 block rounded border px-3 py-1 text-sm">
                @foreach($periods as $item)
                    <option value="{{ $item->id }}">{{ $item->year }}-{{ str_pad((string) $item->month, 2, '0', STR_PAD_LEFT) }}</option>
                @endforeach
            </select>
        </label>
    </div>

    @if($notice !== '')
        <p class="rounded bg-green-100 px-4 py-2 text-sm text-green-700">{{ $notice }}</p>
    @endif
    @if($error !== '')
        <p class="rounded bg-red-100 px-4 py-2 text-sm text-red-700">{{ $error }}</p>
    @endif

    <div class="flex flex-wrap gap-2 text-xs">
        <button type="button" wire:click="$set('tab', 'missing')" class="rounded border px-3 py-1 {{ $tab === 'missing' ? 'border-blue-600 text-blue-700' : 'text-gray-600' }}">Missing punch ({{ $counts['missing'] }})</button>
        <button type="button" wire:click="$set('tab', 'profile')" class="rounded border px-3 py-1 {{ $tab === 'profile' ? 'border-blue-600 text-blue-700' : 'text-gray-600' }}">No time profile ({{ $counts['profile'] }})</button>
        <button type="button" wire:click="$set('tab', 'short')" class="rounded border px-3 py-1 {{ $tab === 'short' ? 'border-blue-600 text-blue-700' : 'text-gray-600' }}">Too little presence ({{ $counts['short'] }})</button>
        <a href="{{ route('attendance.unresolved.index') }}" class="rounded border px-3 py-1 text-gray-600 hover:text-blue-700">Unidentified punches ({{ $counts['unidentified'] }})</a>
        <a href="{{ route('approvals.index') }}" class="rounded border px-3 py-1 text-gray-600 hover:text-blue-700">Pending adjustments ({{ $counts['pending'] }})</a>
    </div>

    @if($tab === 'missing' || $tab === 'short')
        <div class="flex flex-wrap items-end gap-2">
            <label class="text-xs text-gray-600">Reason for ticked rows
                <input type="text" wire:model="bulkReason" class="mt-1 block rounded border px-3 py-1 text-sm" />
            </label>
            <button type="button" wire:click="addSelected" class="rounded bg-blue-600 px-3 py-1 text-xs text-white">Add punch</button>
        </div>
    @endif

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="w-full table-compact text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    @if($tab === 'missing')
                        <th class="px-3 py-2"></th>
                    @endif
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Employee no.</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Name</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Date</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">First in</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($rows as $row)
                    <tr class="hover:bg-gray-50" wire:key="ex-{{ $row['kind'] }}-{{ $row['id'] ?? $row['employment_id'] }}">
                        @if($tab === 'missing')
                            <td class="px-3 py-1.5"><input type="checkbox" wire:model="selected" value="{{ $row['id'] }}" /></td>
                        @endif
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $row['employee_no'] }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $row['name'] }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $row['date'] ?? '' }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $row['first_in'] ?? '' }}</td>
                        <td class="px-3 py-1.5 text-xs">
                            @if(($row['kind'] ?? '') === 'profile')
                                <a href="{{ $row['href'] }}" class="text-blue-700 hover:underline">Time</a>
                            @elseif($tab === 'missing')
                                <button type="button" wire:click="openAdd({{ $row['id'] }})" class="text-blue-700 hover:underline">Add punch</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-sm text-gray-500">Nothing in this list.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($activeTimecardId)
        <div class="rounded bg-white p-4 shadow">
            <h3 class="text-sm font-semibold text-gray-800">Add punch</h3>
            <label class="mt-3 block text-xs text-gray-600">Time
                <input type="text" wire:model="punchedAt" class="mt-1 w-full max-w-xs rounded border px-3 py-2 text-sm" />
            </label>
            <label class="mt-3 block text-xs text-gray-600">Reason
                <input type="text" wire:model="reason" class="mt-1 w-full max-w-md rounded border px-3 py-2 text-sm" />
            </label>
            <button type="button" wire:click="saveAdd" class="mt-3 rounded bg-blue-600 px-3 py-1 text-xs text-white">Save</button>
        </div>
    @endif
</div>
