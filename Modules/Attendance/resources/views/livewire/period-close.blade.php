<div class="mx-auto max-w-[960px] space-y-4 px-6 py-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-lg font-semibold text-gray-800">Period close</h1>
        <label class="text-xs text-gray-600">Period
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

    @if($period)
        <div class="rounded bg-white shadow">
            <div class="flex items-center justify-between border-b px-5 py-3">
                <h2 class="text-sm font-semibold text-gray-700">Checklist</h2>
                <button type="button" wire:click="refreshChecklist" class="rounded border px-3 py-1 text-xs text-gray-700">Refresh</button>
            </div>
            <ul class="divide-y">
                @foreach($items as $item)
                    <li class="flex items-center justify-between px-5 py-2 text-sm">
                        <span>
                            @if(isset($links[$item->code]) && ! $item->ok)
                                <a href="{{ $links[$item->code] }}" class="text-blue-700 hover:underline">{{ $item->title }}</a>
                            @else
                                {{ $item->title }}
                            @endif
                            @unless($item->blocking)
                                <span class="text-xs text-gray-400">Warning</span>
                            @endunless
                        </span>
                        <span class="{{ $item->ok ? 'text-green-700' : 'text-red-700' }}">{{ $item->count }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="border-t px-5 py-3">
                @can('period.close')
                    @if($period->status === \Modules\Calendar\Enums\PeriodStatus::OPEN)
                        <button id="close-period" type="button" wire:click="askClose" @disabled(! $canClose) class="rounded bg-blue-600 px-3 py-1 text-xs text-white">Close period</button>
                    @endif
                @endcan
            </div>
        </div>

        @if($confirming)
            <div class="rounded border border-amber-300 bg-amber-50 px-5 py-4 text-sm text-gray-800">
                <p>{{ $confirmText }}</p>
                <div class="mt-3 flex gap-2">
                    <button type="button" wire:click="closePeriod" class="rounded bg-blue-600 px-3 py-1 text-xs text-white">Confirm close</button>
                    <button type="button" wire:click="$set('confirming', false)" class="rounded border px-3 py-1 text-xs">Cancel</button>
                </div>
            </div>
        @endif

        @if($period->status === \Modules\Calendar\Enums\PeriodStatus::CLOSED)
            <div class="rounded bg-white px-5 py-4 text-sm text-gray-700 shadow">
                <p>Closed by {{ $closer }} on {{ $period->closed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}. Version {{ $period->close_version }}.</p>
                @can('period.reopen')
                    <label class="mt-3 block text-xs text-gray-600">Reason to reopen
                        <input type="text" wire:model="reopenReason" class="mt-1 w-full rounded border px-3 py-2 text-sm" />
                    </label>
                    <button type="button" wire:click="reopenPeriod" class="mt-3 rounded border border-blue-600 px-3 py-1 text-xs text-blue-700">Reopen</button>
                @endcan
            </div>
        @endif

        <div class="rounded bg-white shadow">
            <h2 class="border-b px-5 py-3 text-sm font-semibold text-gray-700">History</h2>
            <table class="w-full table-compact text-sm">
                <thead class="border-b bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Event</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Version</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Reason</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($events as $event)
                        <tr>
                            <td class="px-3 py-1.5 text-xs text-gray-700">{{ $event->event }}</td>
                            <td class="px-3 py-1.5 text-xs text-gray-700">{{ $event->version }}</td>
                            <td class="px-3 py-1.5 text-xs text-gray-700">{{ $event->reason }}</td>
                            <td class="px-3 py-1.5 text-xs text-gray-700">{{ $event->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-4 text-center text-sm text-gray-500">No events yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
