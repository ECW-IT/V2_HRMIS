<div class="p-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Holidays</h1>
            <p class="text-sm text-slate-500">Company holidays used by timecards and overtime.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                Year
                <input type="number" wire:model.live="year" min="2000" max="2100" class="w-24 rounded border border-slate-300 px-2 py-1.5 text-sm">
            </label>
            @can('calendar.manage')
                <button type="button" wire:click="openCopy" class="rounded border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                    Copy from another company
                </button>
                <button type="button" wire:click="openCreate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">
                    Add holiday
                </button>
            @endcan
        </div>
    </div>

    @if ($companyId === null)
        <div class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            Select a company first.
        </div>
    @else
        @if ($formError !== '')
            <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
        @endif
        @if ($copyMessage !== '')
            <div class="mb-3 rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ $copyMessage }}</div>
        @endif
        @if ($importMessage !== '')
            <div class="mb-3 rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ $importMessage }}</div>
        @endif

        <div class="overflow-hidden rounded border bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Weekday</th>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Kind</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($holidays as $holiday)
                        <tr class="border-t" wire:key="holiday-{{ $holiday->id }}">
                            <td class="px-3 py-2 font-medium">{{ $holiday->date->format('Y-m-d') }}</td>
                            <td class="px-3 py-2">{{ $holiday->date->format('l') }}</td>
                            <td class="px-3 py-2">{{ $holiday->name }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                    {{ $holiday->kind->value }}
                                </span>
                            </td>
                            <td class="space-x-2 whitespace-nowrap px-3 py-2 text-right">
                                @can('calendar.manage')
                                    <button type="button" wire:click="openEdit({{ $holiday->id }})" class="text-[var(--color-erp-accent)] hover:underline">Edit</button>
                                    <button type="button" wire:click="remove({{ $holiday->id }})" class="text-red-600 hover:underline" wire:confirm="Remove this holiday?">Remove</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-slate-500">No holidays for this year.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @can('calendar.manage')
            <div class="mt-6 rounded border bg-white p-4">
                <h2 class="text-base font-semibold text-slate-800">Import from Excel</h2>
                <p class="mt-1 text-sm text-slate-500">Columns: date, name, kind. Preview must be error-free before saving.</p>
                <div class="mt-3 flex flex-wrap items-end gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">File</label>
                        <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv" class="block text-sm">
                        @error('importFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="button" wire:click="previewImport" class="rounded border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                        Preview
                    </button>
                    @if ($importRows !== [] && $importErrors === [])
                        <button type="button" wire:click="commitImport" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">
                            Save import
                        </button>
                    @endif
                </div>

                @if ($importErrors !== [])
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-red-700">
                        @foreach ($importErrors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($importRows !== [])
                    <div class="mt-3 overflow-hidden rounded border">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-3 py-2">Row</th>
                                    <th class="px-3 py-2">Date</th>
                                    <th class="px-3 py-2">Name</th>
                                    <th class="px-3 py-2">Kind</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($importRows as $row)
                                    <tr class="border-t">
                                        <td class="px-3 py-2">{{ $row['row'] }}</td>
                                        <td class="px-3 py-2">{{ $row['date'] }}</td>
                                        <td class="px-3 py-2">{{ $row['name'] }}</td>
                                        <td class="px-3 py-2">{{ $row['kind'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endcan
    @endif

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-4 shadow-xl">
                <h2 class="text-base font-semibold text-slate-800">{{ $editingId ? 'Edit holiday' : 'Add holiday' }}</h2>
                @if ($formError !== '')
                    <p class="mt-2 text-sm text-red-600">{{ $formError }}</p>
                @endif
                <div class="mt-3 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Date</label>
                        <input type="date" wire:model="date" @disabled($editingId) class="w-full rounded border px-3 py-2 text-sm disabled:bg-slate-100">
                        @error('date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Name</label>
                        <input type="text" wire:model="name" class="w-full rounded border px-3 py-2 text-sm">
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Kind</label>
                        <select wire:model="kind" class="w-full rounded border px-3 py-2 text-sm">
                            <option value="">Select kind</option>
                            @foreach ($kinds as $kindOption)
                                <option value="{{ $kindOption->value }}">{{ $kindOption->value }}</option>
                            @endforeach
                        </select>
                        @error('kind') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                    <button type="button" wire:click="save" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showCopy)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-4 shadow-xl">
                <h2 class="text-base font-semibold text-slate-800">Copy from another company</h2>
                <div class="mt-3 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">From company</label>
                        <select wire:model="copyFromCompanyId" class="w-full rounded border px-3 py-2 text-sm">
                            <option value="">Select company</option>
                            @foreach ($otherCompanies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                        @error('copyFromCompanyId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Year</label>
                        <input type="number" wire:model="copyYear" min="2000" max="2100" class="w-full rounded border px-3 py-2 text-sm">
                        @error('copyYear') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closeCopy" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                    <button type="button" wire:click="copyFromCompany" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Copy</button>
                </div>
            </div>
        </div>
    @endif
</div>
