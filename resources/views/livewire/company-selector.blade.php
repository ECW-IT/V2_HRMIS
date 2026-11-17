<div>
    @if ($companies->isNotEmpty())
        <label class="sr-only" for="company-selector">Company</label>
        <select
            id="company-selector"
            wire:model.live="selectedCompanyId"
            class="max-w-[12rem] truncate rounded border px-2 py-1 text-xs focus:outline-none {{ $lightNav
                ? 'border-gray-300 bg-white text-gray-800'
                : 'border-white/20 bg-white/10 text-white' }}"
        >
            @if ($showAllOption)
                <option value="" class="text-gray-800">All companies</option>
            @endif
            @foreach ($companies as $company)
                <option value="{{ $company->id }}" class="text-gray-800">{{ $company->name }}</option>
            @endforeach
        </select>
    @endif
</div>
