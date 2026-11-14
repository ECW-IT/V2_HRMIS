<div class="mx-auto max-w-[1280px] space-y-4 px-6 py-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-lg font-semibold text-gray-800">Period summary</h1>
        <div class="flex flex-wrap items-end gap-3">
            <label class="text-xs text-gray-600">Period
                <select wire:model.live="periodId" class="mt-1 block rounded border px-3 py-1 text-sm">
                    @foreach($periods as $item)
                        <option value="{{ $item->id }}">{{ $item->year }}-{{ str_pad((string) $item->month, 2, '0', STR_PAD_LEFT) }}</option>
                    @endforeach
                </select>
            </label>
            @if($versions->count() > 1)
                <label class="text-xs text-gray-600">Version
                    <select wire:model.live="version" class="mt-1 block rounded border px-3 py-1 text-sm">
                        @foreach($versions as $item)
                            <option value="{{ $item }}">Version {{ $item }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <button type="button" wire:click="export" class="rounded border border-blue-600 px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50">Export</button>
        </div>
    </div>

    @if($banner)
        <p class="rounded border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900">{{ $banner }}</p>
    @endif
    @if($error !== '')
        <p class="rounded bg-red-100 px-4 py-2 text-sm text-red-700">{{ $error }}</p>
    @endif

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="w-full table-compact text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    @foreach($headings as $heading)
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">{{ $heading }}</th>
                    @endforeach
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($rows as $row)
                    <tr class="hover:bg-gray-50" wire:key="sum-{{ $row['employment_id'] ?? 'total' }}">
                        @foreach($row['cells'] as $index => $cell)
                            @php
                                $highlight = ($index === 15 && $row['nopay_highlight']) || ($index === 17 && $row['late_highlight']);
                                $title = $index === 15 ? $row['nopay_title'] : ($index === 17 ? $row['late_title'] : null);
                            @endphp
                            <td @class(['bg-amber-100' => $highlight, 'px-3 py-1.5 text-xs text-gray-700']) @if($title) title="{{ $title }}" @endif>{{ $cell }}</td>
                        @endforeach
                        <td class="px-3 py-1.5 text-xs">
                            @if($row['employment_id'] && $period && $period->status === \Modules\Calendar\Enums\PeriodStatus::OPEN)
                                @can('period.override')
                                    <button type="button" wire:click="openOverride({{ $row['employment_id'] }})" class="text-blue-700 hover:underline">Override</button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($showOverride)
        @include('attendance::livewire.dialogs.set-override')
    @endif
</div>
