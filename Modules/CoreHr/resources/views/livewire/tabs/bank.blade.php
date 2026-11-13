<div class="space-y-4">
    @include('corehr::partials.action-feedback')

    <div class="rounded bg-white p-5 shadow">
        <div class="mb-4 flex items-center justify-between border-b pb-2">
            <h3 class="text-sm font-semibold text-gray-700">Current account</h3>
            @can('employee.bank.manage')
                @if(! $showForm)
                    <button type="button" wire:click="openForm" class="rounded border border-blue-600 px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50">
                        Change bank account
                    </button>
                @endif
            @endcan
        </div>

        @if($current)
            @if($current->is_cash)
                <p class="text-sm text-gray-700">Paid in cash since {{ $current->effective_from->format('Y-m-d') }}.</p>
            @else
                <dl class="grid grid-cols-3 gap-y-2 text-sm">
                    <dt class="text-xs text-gray-500">Bank</dt>
                    <dd class="col-span-2 text-gray-700">{{ $current->bank?->name }}</dd>
                    <dt class="text-xs text-gray-500">Branch</dt>
                    <dd class="col-span-2 text-gray-700">{{ $current->branch?->name }}</dd>
                    <dt class="text-xs text-gray-500">Account no.</dt>
                    <dd class="col-span-2 font-mono text-xs text-gray-700">{{ $current->account_no }}</dd>
                    <dt class="text-xs text-gray-500">Account name</dt>
                    <dd class="col-span-2 text-gray-700">{{ $current->account_name }}</dd>
                    <dt class="text-xs text-gray-500">Since</dt>
                    <dd class="col-span-2 text-gray-700">{{ $current->effective_from->format('Y-m-d') }}</dd>
                </dl>
            @endif
        @else
            <p class="text-sm text-gray-500">No payment details are recorded for today.</p>
        @endif
    </div>

    @if($showForm)
        <div class="rounded bg-white p-5 shadow">
            <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Change bank account</h3>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Effective from <span class="text-red-500">*</span></label>
                    <input wire:model="effectiveFrom" type="date"
                        class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('effectiveFrom') border-red-400 @enderror" />
                    @error('effectiveFrom')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model.live="isCash" class="rounded border" />
                        Paid in cash
                    </label>
                </div>

                @if(! $isCash)
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Bank <span class="text-red-500">*</span></label>
                        <select wire:model.live="bankId" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('bankId') border-red-400 @enderror">
                            <option value="">— Select —</option>
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                            @endforeach
                        </select>
                        @error('bankId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Branch <span class="text-red-500">*</span></label>
                        <select wire:model="bankBranchId" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('bankBranchId') border-red-400 @enderror">
                            <option value="">— Select —</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('bankBranchId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Account no. <span class="text-red-500">*</span></label>
                        <input wire:model="accountNo" type="text" maxlength="30"
                            class="w-full rounded border px-3 py-1.5 font-mono text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('accountNo') border-red-400 @enderror" />
                        @error('accountNo')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Account name <span class="text-red-500">*</span></label>
                        <input wire:model="accountName" type="text" maxlength="150"
                            class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('accountName') border-red-400 @enderror" />
                        @error('accountName')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                @endif
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" wire:click="closeForm" class="rounded border px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                    class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Save</button>
            </div>
        </div>
    @endif

    <div class="overflow-x-auto rounded bg-white shadow">
        <div class="border-b px-5 py-3">
            <h3 class="text-sm font-semibold text-gray-700">History</h3>
        </div>
        <table class="w-full table-compact text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">From</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">To</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Bank</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Branch</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Account no.</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Account name</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($history as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5 text-xs text-gray-600">{{ $row->effective_from->format('Y-m-d') }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-600">{{ $row->effective_to?->format('Y-m-d') ?? 'Present' }}</td>
                        @if($row->is_cash)
                            <td colspan="4" class="px-3 py-1.5 text-xs text-gray-600">Cash</td>
                        @else
                            <td class="px-3 py-1.5 text-xs text-gray-600">{{ $row->bank?->name }}</td>
                            <td class="px-3 py-1.5 text-xs text-gray-600">{{ $row->branch?->name }}</td>
                            <td class="px-3 py-1.5 font-mono text-xs text-gray-600">{{ $row->account_no }}</td>
                            <td class="px-3 py-1.5 text-xs text-gray-600">{{ $row->account_name }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-sm text-gray-500">No bank accounts recorded.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
