<div class="space-y-4">
    <div class="rounded bg-white shadow">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Timecard</h3>
                <p class="text-xs text-gray-500">{{ $employment->employee_no }} · {{ $employment->person?->name_with_initials }}</p>
            </div>
            <div class="flex items-center gap-2">
                @if($closedLabel)
                    <span class="rounded bg-gray-200 px-2 py-1 text-xs font-medium text-gray-700">{{ $closedLabel }}</span>
                @endif
                <input type="month" wire:model.live="month" aria-label="Month" class="rounded border px-3 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500" />
                <button type="button" wire:click="export" class="rounded border border-blue-600 px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50">Export</button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full table-compact text-sm">
                <thead class="border-b bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Date</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">First in</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Last out</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Day</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Day value</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Late minutes</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">OT minutes</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($rows as $row)
                        <tr class="hover:bg-gray-50" wire:key="day-{{ $row['date'] }}">
                            <td class="px-3 py-1.5 text-xs text-gray-700">
                                <button type="button" wire:click="showDetail('{{ $row['date'] }}')" class="text-blue-700 hover:underline">{{ $row['date'] }}</button>
                                @if($row['pending'])
                                    <div class="text-gray-400">Pending adjustment</div>
                                @endif
                            </td>
                            <td class="px-3 py-1.5 text-xs text-gray-700">{{ $row['first_in'] }}</td>
                            <td class="px-3 py-1.5 text-xs text-gray-700">{{ $row['last_out'] }}</td>
                            <td class="px-3 py-1.5 text-xs text-gray-700">{{ $row['day'] }}</td>
                            <td class="px-3 py-1.5 text-right text-xs text-gray-700">{{ $row['day_value'] }}</td>
                            <td class="px-3 py-1.5 text-right text-xs text-gray-700">{{ $row['late'] }}</td>
                            <td class="px-3 py-1.5 text-right text-xs text-gray-700">{{ $row['ot'] }}</td>
                            <td class="px-3 py-1.5 text-xs">
                                @if(! $locked)
                                    @can('attendance.adjust')
                                        <button type="button" wire:click="openAdd('{{ $row['date'] }}')" class="mr-2 text-blue-700 hover:underline">Add punch</button>
                                    @endcan
                                    @if($row['can_waive'])
                                        @can('late.waive')
                                            <button type="button" wire:click="openWaive({{ $row['timecard_id'] }})" class="mr-2 text-blue-700 hover:underline">Waive late</button>
                                        @endcan
                                    @endif
                                    @if($row['can_ot'])
                                        @can('ot.approve')
                                            <button type="button" wire:click="openOt({{ $row['timecard_id'] }})" class="text-blue-700 hover:underline">Approve OT</button>
                                        @endcan
                                    @endif
                                @endif
                            </td>
                        </tr>
                        @if($detailDate === $row['date'])
                            <tr class="bg-gray-50" wire:key="detail-{{ $row['date'] }}">
                                <td colspan="8" class="px-3 py-2">
                                    <table class="w-full text-xs">
                                        <thead>
                                            <tr class="text-left text-gray-500">
                                                <th class="py-1">Time</th>
                                                <th class="py-1">Device</th>
                                                <th class="py-1"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($detailPunches as $punch)
                                                <tr>
                                                    <td class="py-1">{{ $punch->punched_at->timezone(config('app.timezone'))->format('H:i') }}</td>
                                                    <td class="py-1">{{ $punch->device?->name ?? 'Manual' }}</td>
                                                    <td class="py-1">
                                                        @if(! $locked)
                                                            @can('attendance.adjust')
                                                                <button type="button" wire:click="openVoid({{ $punch->id }})" class="text-blue-700 hover:underline">Void punch</button>
                                                            @endcan
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="3" class="py-1 text-gray-500">No punches this day.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                    @foreach($detailAdjustments as $adjustment)
                                        <p class="mt-1 text-xs text-gray-500">{{ $this->adjustmentLabel($adjustment->type) }} · {{ $adjustment->status->value }} · {{ $adjustment->reason }}</p>
                                    @endforeach
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap gap-6 border-t px-5 py-3 text-sm text-gray-700">
            @if($summaryError)
                <span>{{ $summaryError }}</span>
            @else
                <span>Worked days <strong>{{ $worked }}</strong></span>
                <span>Expected working days <strong>{{ $expected }}</strong></span>
                <span>No-pay days <strong>{{ $nopay }}</strong></span>
            @endif
        </div>
    </div>

    @if($showAdd)
        @include('attendance::livewire.dialogs.add-punch')
    @endif
    @if($showVoid)
        @include('attendance::livewire.dialogs.void-punch')
    @endif
    @if($showWaive)
        @include('attendance::livewire.dialogs.waive-late')
    @endif
    @if($showOt)
        @include('attendance::livewire.dialogs.approve-ot')
    @endif
</div>
