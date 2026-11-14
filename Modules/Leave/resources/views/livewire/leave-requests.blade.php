<div class="mx-auto max-w-[1280px] space-y-4 px-6 py-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-lg font-semibold text-gray-800">Leave requests</h1>
        @can('leave.request')
            <button type="button" wire:click="openForm" class="rounded bg-blue-600 px-3 py-1 text-xs text-white">New leave</button>
        @endcan
    </div>

    @if($error !== '')
        <p class="rounded bg-red-100 px-4 py-2 text-sm text-red-700">{{ $error }}</p>
    @endif

    <div class="flex flex-wrap gap-3 text-xs text-gray-700">
        @foreach(['PENDING' => 'Pending', 'APPROVED' => 'Approved', 'REJECTED' => 'Rejected', 'CANCELLED' => 'Cancelled'] as $value => $label)
            <label class="flex items-center gap-1">
                <input type="checkbox" value="{{ $value }}" wire:model.live="statuses" />
                {{ $label }}
            </label>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="w-full table-compact text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Employee no.</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Name</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Date</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Type</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Portion</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Status</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($requests as $request)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $request->employment?->employee_no }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $request->employment?->person?->name_with_initials }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $request->date->toDateString() }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $request->leaveType?->name }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $request->portion->value }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ ucfirst(strtolower($request->status->value)) }}</td>
                        <td class="px-3 py-1.5 text-xs">
                            @if(in_array($request->status->value, ['PENDING', 'APPROVED'], true))
                                <button type="button" wire:click="cancel({{ $request->id }})" class="text-blue-700 hover:underline">Cancel</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center text-sm text-gray-500">No leave requests.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($showForm)
        @include('leave::livewire.dialogs.request-leave')
    @endif
</div>
