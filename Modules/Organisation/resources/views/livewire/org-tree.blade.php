<div class="p-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Department break down</h1>
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
            Select a company first (use the company dropdown in the top bar).
        </div>
    @else
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Search by code or name"
            class="mb-3 w-full max-w-sm rounded border border-slate-300 px-3 py-2 text-sm"
        >

        <div class="overflow-hidden rounded border bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Unit</th>
                        <th class="px-3 py-2">Type</th>
                        {{-- <th class="px-3 py-2">Status</th> --}}
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tree as $row)
                        @php($unit = $row['unit'])
                        <tr class="border-t" wire:key="unit-{{ $unit->id }}">
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2" style="padding-left: {{ $row['depth'] * 1.25 }}rem">
                                    @if ($row['hasChildren'])
                                        <button type="button" wire:click="toggleExpanded({{ $unit->id }})" class="text-slate-500 hover:text-slate-800">
                                            {{ ($expanded[$unit->id] ?? false) ? '▾' : '▸' }}
                                        </button>
                                    @else
                                        <span class="inline-block w-4"></span>
                                    @endif
                                    <span class="font-medium">{{ $unit->code }}</span>
                                    <span class="text-slate-600">{{ $unit->name }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                    @switch($unit->type)
                                        @case(\Modules\Organisation\Enums\OrgUnitType::DEPARTMENT)
                                            Department
                                            @break
                                        @case(\Modules\Organisation\Enums\OrgUnitType::SECTION)
                                            Section
                                            @break
                                        @case(\Modules\Organisation\Enums\OrgUnitType::SUBSECTION)
                                            Sub section
                                            @break
                                        @default
                                            {{ $unit->type->value }}
                                    @endswitch
                                </span>
                            </td>
                            {{-- <td class="px-3 py-2">{{ $unit->is_active ? 'Active' : 'Inactive' }}</td> --}}
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                @can('org.manage')
                                    <div class="inline-flex items-center gap-1.5">
                                        @if ($unit->type === \Modules\Organisation\Enums\OrgUnitType::DEPARTMENT)
                                            <x-action-icon-button variant="add" label="Add section" wire:click="openAddChild({{ $unit->id }})" />
                                        @elseif ($unit->type === \Modules\Organisation\Enums\OrgUnitType::SECTION)
                                            <x-action-icon-button variant="add" label="Add sub section" wire:click="openAddChild({{ $unit->id }})" />
                                        @endif
                                        <x-action-icon-button variant="edit" wire:click="openEdit({{ $unit->id }})" />
                                        <x-action-icon-button variant="move" wire:click="openMove({{ $unit->id }})" />
                                        @if ($unit->is_active)
                                            <x-action-icon-button variant="deactivate" wire:click="deactivate({{ $unit->id }})" />
                                        @else
                                            <x-action-icon-button variant="activate" wire:click="activate({{ $unit->id }})" />
                                        @endif
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">No departments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endunless

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-4 shadow-xl">
                <h2 class="text-base font-semibold text-slate-800">{{ $formTitle }}</h2>

                <div class="mt-3 space-y-3">
                    @if ($formMode === 'move')
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">New parent</label>
                            <div
                                x-data="moveParentPicker({
                                    selectedId: {{ $moveParentId === null ? 'null' : (int) $moveParentId }},
                                    allowNoParent: {{ $allowNoParent ? 'true' : 'false' }},
                                    options: {{ \Illuminate\Support\Js::from($moveParentOptions) }}
                                })"
                                class="relative"
                                x-on:click.outside="open = false"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between rounded border border-[var(--color-erp-border)] bg-white px-3 py-2 text-left text-sm text-slate-800"
                                    x-on:click="open = !open"
                                    aria-haspopup="listbox"
                                    x-bind:aria-expanded="open"
                                >
                                    <span class="truncate" x-text="selectedLabel"></span>
                                    <svg class="h-4 w-4 shrink-0 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </button>
                                <div
                                    x-show="open"
                                    x-cloak
                                    class="popup absolute z-10 mt-1 w-full border border-[var(--color-erp-border)] bg-white shadow-lg"
                                    role="listbox"
                                >
                                    <div class="border-b border-[var(--color-erp-border)] p-2">
                                        <input
                                            type="search"
                                            x-model="query"
                                            x-on:keydown.escape.stop="open = false"
                                            placeholder="Search parent"
                                            class="w-full rounded border border-[var(--color-erp-border)] px-2 py-1.5 text-sm"
                                            aria-label="Search parent"
                                        >
                                    </div>
                                    <ul class="max-h-48 overflow-y-auto py-1 text-sm">
                                        <template x-if="allowNoParent">
                                            <li>
                                                <button
                                                    type="button"
                                                    class="block w-full px-3 py-1.5 text-left hover:bg-[var(--color-erp-row-hover)]"
                                                    x-on:click="select(null)"
                                                >No parent (top level)</button>
                                            </li>
                                        </template>
                                        <template x-for="opt in filtered" :key="opt.id">
                                            <li>
                                                <button
                                                    type="button"
                                                    class="block w-full px-3 py-1.5 text-left hover:bg-[var(--color-erp-row-hover)]"
                                                    x-on:click="select(opt.id)"
                                                    x-text="opt.label"
                                                ></button>
                                            </li>
                                        </template>
                                        <li x-show="filtered.length === 0" class="px-3 py-2 text-slate-500">No matches</li>
                                    </ul>
                                </div>
                            </div>
                            @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @else
                        @if ($parentLabel !== '')
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Parent</label>
                                <p class="rounded border bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ $parentLabel }}</p>
                            </div>
                        @endif
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
                            @if ($formMode === 'create')
                                <p class="rounded border bg-slate-50 px-3 py-2 text-sm capitalize text-slate-700">{{ $typeLabel }}</p>
                                <input type="hidden" wire:model="type">
                            @else
                                <select wire:model="type" class="w-full rounded border px-3 py-2 text-sm">
                                    @foreach ($types as $case)
                                        <option value="{{ $case->value }}">{{ $case->value }}</option>
                                    @endforeach
                                </select>
                            @endif
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
