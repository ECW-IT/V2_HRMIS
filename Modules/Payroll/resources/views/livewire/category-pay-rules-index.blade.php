<div class="p-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Category pay rules</h1>
            <p class="text-sm text-slate-500">How an extra day is paid for each employee category.</p>
        </div>
        @if ($canConfigure)
            <button type="button" wire:click="openCreate" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">New rule</button>
        @endif
    </div>

    <div class="overflow-hidden rounded bg-white shadow">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2">Category</th>
                    <th class="px-3 py-2">Extra day</th>
                    <th class="px-3 py-2">From</th>
                    <th class="px-3 py-2">To</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rules as $rule)
                    <tr class="border-t">
                        <td class="px-3 py-2 font-medium">{{ $rule->employeeCategory?->code }}</td>
                        <td class="px-3 py-2">{{ $rule->extra_day_method->value }}</td>
                        <td class="px-3 py-2">{{ $rule->effective_from->toDateString() }}</td>
                        <td class="px-3 py-2">{{ $rule->effective_to?->toDateString() ?? 'Open' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">No category pay rules yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="mt-4 rounded bg-white p-4 shadow">
            @if ($formError)
                <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $formError }}</div>
            @endif
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Category</label>
                    <select wire:model="employee_category_id" class="w-full rounded border px-3 py-2 text-sm">
                        <option value="">Choose a category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->code }} — {{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Extra day method</label>
                    <select wire:model="extra_day_method" class="w-full rounded border px-3 py-2 text-sm">
                        @foreach ($methods as $method)
                            <option value="{{ $method->value }}">{{ $method->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Effective from</label>
                    <input type="date" wire:model="effective_from" class="w-full rounded border px-3 py-2 text-sm">
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="closeForm" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                <button type="button" wire:click="save" class="rounded bg-[var(--color-erp-accent)] px-3 py-1.5 text-sm text-white">Save</button>
            </div>
        </div>
    @endif
</div>
