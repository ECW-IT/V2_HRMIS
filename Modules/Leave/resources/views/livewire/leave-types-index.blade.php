<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-slate-900">Leave types</h1>
        <a href="{{ route('leave.policies.index') }}" class="text-sm text-sky-700 hover:underline">Leave policies</a>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                    <th class="px-3 py-2">Code</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Credited</th>
                    <th class="px-3 py-2">Half day</th>
                    <th class="px-3 py-2">Enforce balance</th>
                    <th class="px-3 py-2">Active</th>
                    @can('leave.manage')
                        <th class="px-3 py-2"></th>
                    @endcan
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($types as $type)
                    <tr>
                        <td class="px-3 py-2">{{ $type->code }}</td>
                        <td class="px-3 py-2">{{ $type->name }}</td>
                        <td class="px-3 py-2">{{ $type->counts_as_credited ? 'Yes' : 'No' }}</td>
                        <td class="px-3 py-2">{{ $type->allows_half_day ? 'Yes' : 'No' }}</td>
                        <td class="px-3 py-2">{{ $type->enforce_balance ? 'Yes' : 'No' }}</td>
                        <td class="px-3 py-2">{{ $type->is_active ? 'Yes' : 'No' }}</td>
                        @can('leave.manage')
                            <td class="px-3 py-2">
                                <button type="button" wire:click="edit({{ $type->id }})" class="text-sky-700 hover:underline">Edit</button>
                            </td>
                        @endcan
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @can('leave.manage')
        <form wire:submit="save" class="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="text-base font-medium text-slate-900">{{ $editingId ? 'Edit leave type' : 'Add leave type' }}</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <label class="block text-sm">
                    <span class="text-slate-700">Code</span>
                    <input type="text" wire:model="code" class="mt-1 w-full rounded border-slate-300" />
                    @error('code') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block text-sm">
                    <span class="text-slate-700">Name</span>
                    <input type="text" wire:model="name" class="mt-1 w-full rounded border-slate-300" />
                    @error('name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
            </div>
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" wire:model="countsAsCredited" class="mt-1 rounded border-slate-300" />
                <span>
                    <span class="font-medium text-slate-800">Counts as credited</span>
                    <span class="mt-1 block text-slate-600">On: a day on this leave is paid like a worked day. Off: it is an approved day off and is not paid.</span>
                </span>
            </label>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="allowsHalfDay" class="rounded border-slate-300" /> Allows half day</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="enforceBalance" class="rounded border-slate-300" /> Enforce balance</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="requiresDocument" class="rounded border-slate-300" /> Requires document</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="isActive" class="rounded border-slate-300" /> Active</label>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded bg-slate-900 px-3 py-2 text-sm text-white">Save</button>
                @if ($editingId)
                    <button type="button" wire:click="resetForm" class="rounded border border-slate-300 px-3 py-2 text-sm">Cancel</button>
                @endif
            </div>
        </form>
    @endcan
</div>
