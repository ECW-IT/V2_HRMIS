@props([
    'suggestions' => [],
    'value' => '',
    'disabled' => false,
    'inputClass' => 'w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500',
])

@if (count($suggestions) > 0)
    <div
        wire:key="gn-division-picker-{{ md5(implode('|', $suggestions).'|'.$value.($disabled ? '-d' : '')) }}"
        x-data="gnDivisionPicker({
            selected: {{ \Illuminate\Support\Js::from($value) }},
            options: {{ \Illuminate\Support\Js::from(array_values($suggestions)) }},
            disabled: {{ $disabled ? 'true' : 'false' }}
        })"
        class="relative"
        x-on:click.outside="open = false"
    >
        <button
            type="button"
            @disabled($disabled)
            class="{{ $inputClass }} flex items-center justify-between text-left {{ $disabled ? 'bg-gray-100' : 'bg-white' }}"
            x-on:click="if (!disabled) open = !open"
            aria-haspopup="listbox"
            aria-label="GN division"
            x-bind:aria-expanded="open"
        >
            <span class="truncate" x-text="selectedLabel"></span>
            <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
        </button>
        <div
            x-show="open"
            x-cloak
            class="absolute z-20 mt-1 w-full rounded border border-gray-200 bg-white shadow-lg"
            role="listbox"
        >
            <div class="border-b border-gray-200 p-2">
                <input
                    type="search"
                    x-model="query"
                    x-on:keydown.escape.stop="open = false"
                    placeholder="Search GN division"
                    class="w-full rounded border px-2 py-1.5 text-sm"
                    aria-label="Search GN division"
                >
            </div>
            <ul class="max-h-48 overflow-y-auto py-1 text-sm">
                <li>
                    <button
                        type="button"
                        class="block w-full px-3 py-1.5 text-left text-gray-500 hover:bg-[var(--color-erp-row-hover)]"
                        x-on:click="select('')"
                    >Clear</button>
                </li>
                <template x-for="name in filtered" :key="name">
                    <li>
                        <button
                            type="button"
                            class="block w-full px-3 py-1.5 text-left hover:bg-[var(--color-erp-row-hover)]"
                            x-on:click="select(name)"
                            x-text="name"
                        ></button>
                    </li>
                </template>
                <li x-show="filtered.length === 0" class="px-3 py-2 text-gray-500">No matches</li>
            </ul>
        </div>
    </div>
@else
    <input
        wire:model="gnDivision"
        type="text"
        maxlength="100"
        @disabled($disabled)
        {{ $attributes->class([$inputClass, 'bg-gray-100' => $disabled]) }}
    />
@endif
