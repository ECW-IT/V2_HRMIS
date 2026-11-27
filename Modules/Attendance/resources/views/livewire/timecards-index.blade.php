<div class="mx-auto max-w-6xl px-6 py-8">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Timecards</h1>
            <p class="text-sm text-slate-500">One row per employee and work date.</p>
        </div>
        @if ($canAdjust)
            <button type="button" wire:click="openCreate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">
                Add timecard
            </button>
        @endif
    </div>

    @if ($listError !== '')
        <p class="mb-4 text-sm text-red-600">{{ $listError }}</p>
    @endif

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <label class="text-xs text-slate-500">From
            <input type="date" wire:model.live="from" class="mt-1 block rounded border px-3 py-1.5 text-sm">
        </label>
        <label class="text-xs text-slate-500">To
            <input type="date" wire:model.live="to" class="mt-1 block rounded border px-3 py-1.5 text-sm">
        </label>
        <label class="text-xs text-slate-500">Search
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Employee number or name" class="mt-1 block w-64 rounded border px-3 py-1.5 text-sm">
        </label>
    </div>

    <div class="overflow-hidden rounded border bg-white">
        <table class="table-compact min-w-full text-left">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Employee no.</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Date</th>
                    <th class="px-3 py-2">Day</th>
                    <th class="px-3 py-2">Value</th>
                    <th class="px-3 py-2">In</th>
                    <th class="px-3 py-2">Out</th>
                    <th class="px-3 py-2">Exception</th>
                    @if ($canAdjust)
                        <th class="px-3 py-2"></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($timecards as $card)
                    <tr class="border-t" wire:key="timecard-{{ $card->id }}">
                        <td class="px-3 py-2">{{ $card->employment?->employee_no }}</td>
                        <td class="px-3 py-2">{{ $card->employment?->person?->name_with_initials }}</td>
                        <td class="px-3 py-2">{{ $card->work_date->format('Y-m-d') }}</td>
                        <td class="px-3 py-2">{{ $card->day_type->value }}</td>
                        <td class="px-3 py-2">{{ $card->day_value }}</td>
                        <td class="px-3 py-2">{{ $card->first_in?->timezone(config('app.timezone'))->format('H:i:s') }}</td>
                        <td class="px-3 py-2">{{ $card->last_out?->timezone(config('app.timezone'))->format('H:i:s') }}</td>
                        <td class="px-3 py-2">{{ $card->exception?->value }}</td>
                        @if ($canAdjust)
                            <td class="px-3 py-2 text-right">
                                <button type="button" wire:click="openEdit({{ $card->id }})" class="text-blue-700 hover:underline">Edit</button>
                                <button type="button" wire:click="deleteTimecard({{ $card->id }})" wire:confirm="Delete this timecard?" class="ml-3 text-red-700 hover:underline">Delete</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="{{ $canAdjust ? 9 : 8 }}" class="px-3 py-4 text-sm text-slate-500">No timecards for this filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $timecards->links() }}
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-black/40 p-4">
            <div class="popup my-8 w-full max-w-2xl rounded border bg-white p-4 shadow-lg">
                <h2 class="mb-3 text-sm font-semibold">{{ $editingId === null ? 'Add timecard' : 'Edit timecard' }}</h2>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">Employee number</label>
                        <input type="text" wire:model="formEmployeeNo" class="w-full rounded border px-3 py-1.5 text-sm">
                        @error('formEmployeeNo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">Work date</label>
                        <input type="date" wire:model="formWorkDate" class="w-full rounded border px-3 py-1.5 text-sm">
                        @error('formWorkDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">Day type</label>
                        <select wire:model="formDayType" class="w-full rounded border px-3 py-1.5 text-sm">
                            @foreach ($dayTypes as $type)
                                <option value="{{ $type->value }}">{{ $type->value }}</option>
                            @endforeach
                        </select>
                        @error('formDayType') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">Day value</label>
                        <input type="number" step="0.01" min="0" max="9.99" wire:model="formDayValue" class="w-full rounded border px-3 py-1.5 text-sm">
                        @error('formDayValue') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">First in</label>
                        <input type="time" step="1" wire:model="formFirstIn" class="w-full rounded border px-3 py-1.5 text-sm">
                        @error('formFirstIn') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">Last out</label>
                        <input type="time" step="1" wire:model="formLastOut" class="w-full rounded border px-3 py-1.5 text-sm">
                        @error('formLastOut') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">Punch count</label>
                        <input type="number" min="0" wire:model="formPunchCount" class="w-full rounded border px-3 py-1.5 text-sm">
                        @error('formPunchCount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">Exception</label>
                        <select wire:model="formException" class="w-full rounded border px-3 py-1.5 text-sm">
                            <option value="">None</option>
                            @foreach ($exceptions as $exception)
                                <option value="{{ $exception->value }}">{{ $exception->value }}</option>
                            @endforeach
                        </select>
                        @error('formException') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">Late minutes</label>
                        <input type="number" min="0" wire:model="formLate" class="w-full rounded border px-3 py-1.5 text-sm">
                        @error('formLate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">Early-out minutes</label>
                        <input type="number" min="0" wire:model="formEarly" class="w-full rounded border px-3 py-1.5 text-sm">
                        @error('formEarly') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">Morning OT minutes</label>
                        <input type="number" min="0" wire:model="formOtMorning" class="w-full rounded border px-3 py-1.5 text-sm">
                        @error('formOtMorning') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500">Evening OT minutes</label>
                        <input type="number" min="0" wire:model="formOtEvening" class="w-full rounded border px-3 py-1.5 text-sm">
                        @error('formOtEvening') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="formShortLeave">
                        Short leave
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="formHoliday">
                        Holiday
                    </label>
                </div>
                @if ($formError !== '')
                    <p class="mt-3 text-xs text-red-600">{{ $formError }}</p>
                @endif
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                    <button type="button" wire:click="saveTimecard" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
