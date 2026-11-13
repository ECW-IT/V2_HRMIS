<div>
    @if ($companies->isNotEmpty())
        <label class="sr-only" for="company-selector">Company</label>
        <select
            id="company-selector"
            wire:model.live="selectedCompanyId"
            class="max-w-[12rem] truncate rounded border border-white/20 bg-white/10 px-2 py-1 text-xs text-white focus:outline-none"
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
