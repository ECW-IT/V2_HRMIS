<div class="p-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Employee categories</h1>
            {{-- <p class="text-sm text-slate-500">A category is a label for grouping employees and for reports. Working times are set on the shift, leave on the leave policy, and pay on the employee's Pay tab.</p> --}}
        </div>
        @can('org.manage')
            <button type="button" wire:click="openCreate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">
                New category
            </button>
        @endcan
    </div>

    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search categories" class="mb-3 w-full max-w-sm rounded border border-slate-300 px-3 py-2 text-sm">

    <div class="overflow-hidden rounded border bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Level</th>
                    {{-- <th class="px-3 py-2">Code</th> --}}
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Early OT</th>
                    <th class="px-3 py-2">Evening OT</th>
                    <th class="px-3 py-2">Late</th>
                    <th class="px-3 py-2">Breaks</th>
                    {{-- <th class="px-3 py-2">Active</th> --}}
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr class="border-t">
                        <td class="px-3 py-2">{{ $category->level }}</td>
                            {{-- <td class="px-3 py-2 font-medium">{{ $category->code }}</td> --}}
                        <td class="px-3 py-2">{{ $category->name }}</td>
                        <td class="px-3 py-2">{{ $category->early_ot ? 'Yes' : 'No' }}</td>
                        <td class="px-3 py-2">{{ $category->evening_ot ? 'Yes' : 'No' }}</td>
                        <td class="px-3 py-2">{{ $category->late_deduction ? 'Yes' : 'No' }}</td>
                        <td class="px-3 py-2">{{ $category->breaks->isEmpty() ? 'None' : $category->breaks->pluck('name')->join(', ') }}</td>
                        {{-- <td class="px-3 py-2">{{ $category->is_active ? 'Yes' : 'No' }}</td> --}}
                        <td class="whitespace-nowrap px-3 py-2 text-right">
                            @can('org.manage')
                                <div class="inline-flex items-center gap-1.5">
                                    <x-action-icon-button variant="edit" wire:click="openEdit({{ $category->id }})" />
                                    @if ($category->is_active)
                                        <x-action-icon-button variant="deactivate" wire:click="toggleActive({{ $category->id }})" />
                                    @else
                                        <x-action-icon-button variant="activate" wire:click="toggleActive({{ $category->id }})" />
                                    @endif
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-slate-500">No categories found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-4 shadow-xl">
                <h2 class="text-base font-semibold text-slate-800">{{ $editingId ? 'Edit category' : 'New category' }}</h2>
                <div class="mt-3 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Level</label>
                        <input type="number" min="1" wire:model="level" class="w-full rounded border px-3 py-2 text-sm">
                        @error('level') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Code</label>
                        <input type="text" wire:model="code" class="w-full rounded border px-3 py-2 text-sm">
                        @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Name</label>
                        <input type="text" wire:model="name" class="w-full rounded border px-3 py-2 text-sm">
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="earlyOt" class="rounded border"> Early OT
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="eveningOt" class="rounded border"> Evening OT
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="lateDeduction" class="rounded border"> Late deduction
                    </label>
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-xs font-medium text-slate-600">Breaks</span>
                            <button type="button" wire:click="addBreak" class="text-xs text-blue-600">Add break</button>
                        </div>
                        @error('breaks') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror
                        @foreach ($breaks as $index => $break)
                            <div class="mb-2 grid grid-cols-2 gap-2" wire:key="break-{{ $index }}">
                                <input type="text" wire:model="breaks.{{ $index }}.name" placeholder="Name" class="rounded border px-2 py-1.5 text-sm">
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" wire:model="breaks.{{ $index }}.is_paid" class="rounded border"> Paid
                                </label>
                                <input type="time" wire:model="breaks.{{ $index }}.start_time" class="rounded border px-2 py-1.5 text-sm">
                                <input type="time" wire:model="breaks.{{ $index }}.end_time" class="rounded border px-2 py-1.5 text-sm">
                                <button type="button" wire:click="removeBreak({{ $index }})" class="col-span-2 text-left text-xs text-red-600">Remove</button>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                    <button type="button" wire:click="save" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
