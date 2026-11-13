<div class="p-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Org units</h1>
            <p class="text-sm text-slate-500">Department, section and subsection tree for the selected company.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" wire:model.live="showInactive"> Show inactive
            </label>
            @can('org.manage')
                @if ($companySelected)
                    <button type="button" wire:click="openAddDepartment" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">
                        Add department
                    </button>
                @endif
            @endcan
        </div>
    </div>

    @error('tree')
        <p class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>
    @enderror

    @unless ($companySelected)
        <div class="rounded border border-amber-200 bg-amber-50 px-4 py-6 text-sm text-amber-800">
            Select a company
        </div>
    @else
        <div class="overflow-hidden rounded border bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Unit</th>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tree as $row)
                        @php($unit = $row['unit'])
                        <tr class="border-t">
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2" style="padding-left: {{ $row['depth'] * 1.25 }}rem">
                                    @if ($row['hasChildren'])
                                        <button type="button" wire:click="toggleExpanded({{ $unit->id }})" class="text-slate-500 hover:text-slate-800">
                                            {{ ($expanded[$unit->id] ?? true) ? '▾' : '▸' }}
                                        </button>
                                    @else
                                        <span class="inline-block w-4"></span>
                                    @endif
                                    <span class="font-medium">{{ $unit->code }}</span>
                                    <span class="text-slate-600">{{ $unit->name }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">{{ $unit->type->value }}</span>
                            </td>
                            <td class="px-3 py-2">{{ $unit->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="px-3 py-2 text-right space-x-2 whitespace-nowrap">
                                @can('org.manage')
                                    @if ($unit->type !== \Modules\Organisation\Enums\OrgUnitType::SUBSECTION)
                                        <button type="button" wire:click="openAddChild({{ $unit->id }})" class="text-[var(--color-erp-accent)] hover:underline">Add child</button>
                                    @endif
                                    <button type="button" wire:click="openEdit({{ $unit->id }})" class="text-[var(--color-erp-accent)] hover:underline">Edit</button>
                                    <button type="button" wire:click="openMove({{ $unit->id }})" class="text-slate-600 hover:underline">Move</button>
                                    @if ($unit->is_active)
                                        <button type="button" wire:click="deactivate({{ $unit->id }})" class="text-slate-600 hover:underline">Deactivate</button>
                                    @else
                                        <button type="button" wire:click="activate({{ $unit->id }})" class="text-slate-600 hover:underline">Activate</button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">No organisation units yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endunless

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-4 shadow-xl">
                <h2 class="text-base font-semibold text-slate-800">
                    @if ($formMode === 'move')
                        Move unit
                    @elseif ($formMode === 'edit')
                        Edit unit
                    @else
                        {{ $parentId ? 'Add child unit' : 'Add department' }}
                    @endif
                </h2>

                <div class="mt-3 space-y-3">
                    @if ($formMode === 'move')
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">New parent</label>
                            <select wire:model="moveParentId" class="w-full rounded border px-3 py-2 text-sm">
                                <option value="">No parent (top level)</option>
                                @foreach ($moveParents as $parent)
                                    <option value="{{ $parent->id }}">{{ $parent->code }} — {{ $parent->name }}</option>
                                @endforeach
                            </select>
                            @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @else
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
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Type</label>
                            <select wire:model="type" class="w-full rounded border px-3 py-2 text-sm" @disabled($formMode === 'create' && $parentId)>
                                @foreach ($types as $case)
                                    <option value="{{ $case->value }}">{{ $case->value }}</option>
                                @endforeach
                            </select>
                            @error('type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                    <button type="button" wire:click="save" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
