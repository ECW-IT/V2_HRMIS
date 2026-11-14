<div class="mx-auto max-w-[1280px] space-y-4 px-6 py-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-lg font-semibold text-gray-800">Leave calendar</h1>
        <div class="flex flex-wrap gap-3">
            <label class="text-xs text-gray-600">Month
                <input type="month" wire:model.live="month" class="mt-1 block rounded border px-3 py-1 text-sm" />
            </label>
            <label class="text-xs text-gray-600">Org unit
                <select wire:model.live="orgUnitId" class="mt-1 block rounded border px-3 py-1 text-sm">
                    <option value="">Choose</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit['id'] }}">{{ $unit['label'] }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>

    @if($orgUnitId === '')
        <p class="text-sm text-gray-500">Choose an org unit.</p>
    @else
        <div class="grid grid-cols-7 gap-2">
            @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $name)
                <div class="text-center text-xs font-semibold text-gray-500">{{ $name }}</div>
            @endforeach
            @foreach($weeks as $week)
                @foreach($week as $cell)
                    <div @class([
                        'min-h-24 rounded border p-1 text-xs',
                        'bg-gray-100 text-gray-300' => ! $cell['in_month'],
                        'bg-gray-200' => $cell['in_month'] && $cell['holiday'],
                        'bg-white' => $cell['in_month'] && ! $cell['holiday'],
                    ])>
                        @if($cell['in_month'])
                            <div class="flex items-center justify-between text-gray-500">
                                <span>{{ $cell['day'] }}</span>
                                @if($cell['count'] > 0)
                                    <span>{{ $cell['count'] }} on leave</span>
                                @endif
                            </div>
                            @if($cell['holiday'])
                                <div class="text-gray-600">{{ $cell['holiday'] }}</div>
                            @endif
                            @foreach($cell['people'] as $person)
                                <div @class([
                                    'mt-1 rounded px-1 py-0.5',
                                    'bg-emerald-200' => $person['status'] === 'Approved' && ! $person['half'],
                                    'bg-emerald-200/50 w-1/2' => $person['half'],
                                ]) @if($person['status'] === 'Pending') style="background-image: repeating-linear-gradient(45deg, #fde68a, #fde68a 4px, #fff 4px, #fff 8px);" @endif>
                                    <div>{{ $person['name'] }}</div>
                                    <div>{{ $person['label'] }}</div>
                                    @if($person['half'])
                                        <div>{{ $person['status'] }}</div>
                                    @endif
                                </div>
                            @endforeach
                        @endif
                    </div>
                @endforeach
            @endforeach
        </div>
    @endif
</div>
