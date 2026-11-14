<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-xl font-semibold text-slate-900">Leave policies</h1>
        <div class="flex items-center gap-4">
            <a href="{{ route('leave.types.index') }}" class="text-sm text-sky-700 hover:underline">Leave types</a>
            <label class="text-sm">
                <span class="text-slate-600">Filter by type</span>
                <select wire:model.live="filterTypeId" class="ml-2 rounded border-slate-300">
                    <option value="">All</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}">{{ $type->code }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                    <th class="px-3 py-2">Leave type</th>
                    <th class="px-3 py-2">Company</th>
                    <th class="px-3 py-2">Grade</th>
                    <th class="px-3 py-2">Employment type</th>
                    <th class="px-3 py-2">Days</th>
                    <th class="px-3 py-2">From</th>
                    <th class="px-3 py-2">To</th>
                    @can('leave.manage')
                        <th class="px-3 py-2"></th>
                    @endcan
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($policies as $policy)
                    <tr>
                        <td class="px-3 py-2">{{ $policy->leaveType?->code }}</td>
                        <td class="px-3 py-2">{{ $policy->company?->name ?? 'Any' }}</td>
                        <td class="px-3 py-2">{{ $policy->grade?->name ?? 'Any' }}</td>
                        <td class="px-3 py-2">{{ $policy->employment_type ?? 'Any' }}</td>
                        <td class="px-3 py-2">{{ $policy->days_per_period }}</td>
                        <td class="px-3 py-2">{{ $policy->effective_from?->toDateString() }}</td>
                        <td class="px-3 py-2">{{ $policy->effective_to?->toDateString() ?? 'Open' }}</td>
                        @can('leave.manage')
                            <td class="px-3 py-2">
                                @if ($policy->effective_to === null)
                                    <button type="button" wire:click="end({{ $policy->id }})" class="text-sky-700 hover:underline">End</button>
                                @endif
                            </td>
                        @endcan
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @can('leave.manage')
        <form wire:submit="save" class="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="text-base font-medium text-slate-900">Add policy</h2>
            <div class="grid gap-4 md:grid-cols-3">
                <label class="block text-sm">
                    <span class="text-slate-700">Leave type</span>
                    <select wire:model="leaveTypeId" class="mt-1 w-full rounded border-slate-300">
                        <option value="">Select</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->id }}">{{ $type->code }} — {{ $type->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-slate-700">Company</span>
                    <select wire:model="companyId" class="mt-1 w-full rounded border-slate-300">
                        <option value="">Any</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-slate-700">Grade</span>
                    <select wire:model="gradeId" class="mt-1 w-full rounded border-slate-300">
                        <option value="">Any</option>
                        @foreach ($grades as $grade)
                            <option value="{{ $grade->id }}">{{ $grade->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-slate-700">Employment type</span>
                    <input type="text" wire:model="employmentType" class="mt-1 w-full rounded border-slate-300" placeholder="Any" />
                </label>
                <label class="block text-sm">
                    <span class="text-slate-700">Days per period</span>
                    <input type="text" wire:model="daysPerPeriod" class="mt-1 w-full rounded border-slate-300" />
                </label>
                <label class="block text-sm">
                    <span class="text-slate-700">Effective from</span>
                    <input type="date" wire:model="effectiveFrom" class="mt-1 w-full rounded border-slate-300" />
                </label>
            </div>
            <button type="submit" class="rounded bg-slate-900 px-3 py-2 text-sm text-white">Add</button>
        </form>
    @endcan

    <div class="space-y-3 rounded-lg border border-slate-200 bg-white p-4">
        <h2 class="text-base font-medium text-slate-900">Who gets what</h2>
        <div class="grid gap-4 md:grid-cols-3">
            <label class="block text-sm">
                <span class="text-slate-700">Employment id</span>
                <input type="number" wire:model="whoEmploymentId" class="mt-1 w-full rounded border-slate-300" />
            </label>
            <label class="block text-sm">
                <span class="text-slate-700">Date</span>
                <input type="date" wire:model="whoDate" class="mt-1 w-full rounded border-slate-300" />
            </label>
            <div class="flex items-end">
                <button type="button" wire:click="whoGetsWhat" class="rounded border border-slate-300 px-3 py-2 text-sm">Show</button>
            </div>
        </div>
        @if ($whoResult)
            <p class="text-sm text-slate-700">{{ $whoResult }}</p>
        @endif
    </div>
</div>
