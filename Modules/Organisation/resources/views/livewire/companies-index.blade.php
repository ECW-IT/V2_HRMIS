<div class="p-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Companies</h1>
            <p class="text-sm text-slate-500">Maintain company master data for employee numbering and statutory numbers.</p>
        </div>
        @can('org.manage')
            <button
                type="button"
                wire:click="openCreate"
                class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90"
            >
                New company
            </button>
        @endcan
    </div>

    <input
        type="search"
        wire:model.live.debounce.300ms="search"
        placeholder="Search companies"
        class="mb-3 w-full max-w-sm rounded border border-slate-300 px-3 py-2 text-sm"
    >

    <div class="overflow-hidden rounded border bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Code</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Prefix</th>
                    <th class="px-3 py-2">Padding</th>
                    <th class="px-3 py-2">Next no.</th>
                    <th class="px-3 py-2">EPF</th>
                    <th class="px-3 py-2">ETF</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($companies as $company)
                    <tr class="border-t">
                        <td class="px-3 py-2 font-medium">{{ $company->code }}</td>
                        <td class="px-3 py-2">{{ $company->name }}</td>
                        <td class="px-3 py-2">{{ $company->employee_no_prefix }}</td>
                        <td class="px-3 py-2">{{ $company->employee_no_padding }}</td>
                        <td class="px-3 py-2">{{ $company->next_employee_seq }}</td>
                        <td class="px-3 py-2">{{ $company->epf_employer_no ?: '—' }}</td>
                        <td class="px-3 py-2">{{ $company->etf_employer_no ?: '—' }}</td>
                        <td class="px-3 py-2">
                            <span @class([
                                'rounded px-2 py-0.5 text-xs font-medium',
                                'bg-emerald-50 text-emerald-700' => $company->is_active,
                                'bg-slate-100 text-slate-500' => ! $company->is_active,
                            ])>
                                {{ $company->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-right space-x-2 whitespace-nowrap">
                            @can('org.manage')
                                <button type="button" wire:click="openEdit({{ $company->id }})" class="text-[var(--color-erp-accent)] hover:underline">
                                    Edit
                                </button>
                                <button type="button" wire:click="toggleActive({{ $company->id }})" class="text-slate-600 hover:underline">
                                    {{ $company->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-3 py-6 text-center text-slate-500">No companies found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-4 shadow-xl">
                <h2 class="text-base font-semibold text-slate-800">{{ $editingId ? 'Edit company' : 'New company' }}</h2>

                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Code</label>
                        <input type="text" wire:model="formCode" class="w-full rounded border px-3 py-2 text-sm">
                        @error('formCode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Name</label>
                        <input type="text" wire:model="formName" class="w-full rounded border px-3 py-2 text-sm">
                        @error('formName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Employee no. prefix</label>
                        <input type="text" wire:model="formPrefix" class="w-full rounded border px-3 py-2 text-sm">
                        @error('formPrefix') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Padding</label>
                        <input type="number" wire:model="formPadding" min="1" max="10" class="w-full rounded border px-3 py-2 text-sm">
                        @error('formPadding') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    @if ($editingId)
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-slate-600">Next employee number</label>
                            <input type="text" value="{{ $formNextSeq }}" disabled class="w-full rounded border bg-slate-50 px-3 py-2 text-sm text-slate-500">
                        </div>
                    @endif
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">EPF employer no.</label>
                        <input type="text" wire:model="formEpf" class="w-full rounded border px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">ETF employer no.</label>
                        <input type="text" wire:model="formEtf" class="w-full rounded border px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-slate-600">TIN</label>
                        <input type="text" wire:model="formTin" class="w-full rounded border px-3 py-2 text-sm">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700 sm:col-span-2">
                        <input type="checkbox" wire:model="formIsActive">
                        Active
                    </label>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                    <button type="button" wire:click="save" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
