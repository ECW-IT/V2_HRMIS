@props([
    'options' => [],
    'value' => '',
    'model' => '',
    'emptyLabel' => 'Select',
    'disabled' => false,
    'inputClass' => 'w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500',
    'ariaLabel' => 'Select',
    'searchPlaceholder' => 'Search',
])

@php
    $normalized = collect($options)
        ->map(fn ($option) => [
            'id' => data_get($option, 'id', data_get($option, 'code')),
            'label' => data_get($option, 'label', data_get($option, 'name')),
        ])
        ->values()
        ->all();
    $keySeed = md5(json_encode($normalized).'|'.$value.'|'.$model.($disabled ? '-d' : ''));
@endphp

<div
    wire:key="searchable-select-{{ $model }}-{{ $keySeed }}"
    x-data="searchableSelect({
        selected: {{ \Illuminate\Support\Js::from($value === null ? '' : $value) }},
        options: {{ \Illuminate\Support\Js::from($normalized) }},
        model: {{ \Illuminate\Support\Js::from($model) }},
        emptyLabel: {{ \Illuminate\Support\Js::from($emptyLabel) }},
        disabled: {{ $disabled ? 'true' : 'false' }}
    })"
    class="relative"
    x-on:click.outside="close()"
>
    <div class="relative">
        <input
            type="text"
            x-ref="input"
            @disabled($disabled)
            class="{{ $inputClass }} pr-8 {{ $disabled ? 'bg-gray-100' : 'bg-white' }}"
            x-bind:value="inputValue"
            x-on:focus="openPanel()"
            x-on:click="openPanel()"
            x-on:input="onInput($event)"
            x-on:keydown.escape.stop="close()"
            x-bind:placeholder="open ? {{ \Illuminate\Support\Js::from($searchPlaceholder) }} : {{ \Illuminate\Support\Js::from($emptyLabel) }}"
            aria-haspopup="listbox"
            aria-label="{{ $ariaLabel }}"
            x-bind:aria-expanded="open"
            autocomplete="off"
            role="combobox"
        >
        <button
            type="button"
            tabindex="-1"
            @disabled($disabled)
            class="absolute inset-y-0 right-0 flex items-center px-2 text-gray-500 {{ $disabled ? 'pointer-events-none' : '' }}"
            x-on:click.prevent="open ? close() : openPanel()"
            aria-label="Toggle options"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
        </button>
    </div>
    <div
        x-show="open"
        x-cloak
        class="absolute z-20 mt-1 w-full rounded border border-gray-200 bg-white shadow-lg"
        role="listbox"
    >
        <ul class="max-h-36 overflow-y-auto py-1 text-sm">
            <li>
                <button
                    type="button"
                    class="block w-full px-3 py-1.5 text-left text-gray-500 hover:bg-[var(--color-erp-row-hover)]"
                    x-on:click="select('')"
                >{{ $emptyLabel }}</button>
            </li>
            <template x-for="option in filtered" x-bind:key="String(option.id)">
                <li>
                    <button
                        type="button"
                        class="block w-full px-3 py-1.5 text-left hover:bg-[var(--color-erp-row-hover)]"
                        x-on:click="select(option.id)"
                        x-text="option.label"
                    ></button>
                </li>
            </template>
            <li x-show="filtered.length === 0" class="px-3 py-2 text-gray-500">No matches</li>
        </ul>
    </div>
</div>
