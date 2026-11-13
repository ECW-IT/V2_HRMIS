{{-- Needs: $selectedManager (?Employment), $managerResults (Collection). Component uses SearchesManagers. --}}
<div>
    <label class="mb-1 block text-xs font-medium text-gray-600">Reports to</label>
    @if($selectedManager)
        <div class="flex items-center justify-between rounded border bg-gray-50 px-3 py-1.5 text-sm text-gray-700">
            <span><span class="font-mono text-xs">{{ $selectedManager->employee_no }}</span> · {{ $selectedManager->person->name_with_initials }}</span>
            <button type="button" wire:click="clearManager" class="text-xs text-red-600 hover:text-red-800">Remove</button>
        </div>
    @else
        <input type="text" wire:model.live.debounce.300ms="managerSearch" placeholder="Search by number or name"
            class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500" />
        @if($managerResults->isNotEmpty())
            <ul class="popup mt-1 max-h-40 overflow-y-auto border bg-white text-sm shadow">
                @foreach($managerResults as $candidate)
                    <li>
                        <button type="button" wire:click="pickManager({{ $candidate->id }})" class="block w-full px-3 py-1.5 text-left hover:bg-gray-50">
                            <span class="font-mono text-xs">{{ $candidate->employee_no }}</span> · {{ $candidate->person->name_with_initials }}
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</div>
