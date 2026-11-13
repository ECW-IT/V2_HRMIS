@props([
    'title'      => '',
    'moduleName' => '',
    'moduleNav'  => [],
])

<x-layouts.app
    :title="$moduleName . ($title ? ' · ' . $title : '') ?: config('app.name')"
    :lightNav="true"
>
    {{-- MODULE MENU: injected into the top bar --}}
    <x-slot:moduleMenu>
        <nav class="flex items-center gap-0.5">
            @foreach($moduleNav as $item)
                @if(!empty($item['children']))
                    {{-- Dropdown menu item --}}
                    <div class="relative" x-data="{ open: false }">
                        <button
                            @click="open = !open"
                            @click.outside="open = false"
                            class="flex items-center gap-1 rounded px-3 py-1 text-xs font-medium transition-colors
                                {{ ($item['active'] ?? false)
                                    ? 'bg-indigo-50 text-indigo-700'
                                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-800' }}"
                        >
                            @isset($item['icon'])
                                <span class="leading-none">{{ $item['icon'] }}</span>
                            @endisset
                            {{ $item['label'] }}
                            <svg class="h-3 w-3 transition-transform duration-150" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div
                            x-show="open"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-1"
                            class="absolute left-0 top-full z-50 mt-0.5 min-w-[160px] popup border bg-white py-1 shadow-lg"
                            style="display: none;"
                        >
                            @foreach($item['children'] as $child)
                                <a
                                    href="{{ $child['route'] ?? '#' }}"
                                    class="block px-3 py-1.5 text-xs text-gray-700 hover:bg-indigo-50 hover:text-indigo-700"
                                    @click="open = false"
                                >
                                    {{ $child['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @else
                    {{-- Plain link --}}
                    <a
                        href="{{ $item['route'] ?? '#' }}"
                        class="flex items-center gap-1.5 rounded px-3 py-1 text-xs font-medium transition-colors
                            {{ ($item['active'] ?? false)
                                ? 'bg-indigo-50 text-indigo-700'
                                : 'text-gray-600 hover:bg-gray-100 hover:text-gray-800' }}"
                    >
                        @isset($item['icon'])
                            <span class="leading-none">{{ $item['icon'] }}</span>
                        @endisset
                        {{ $item['label'] }}
                    </a>
                @endif
            @endforeach
        </nav>
    </x-slot:moduleMenu>

    <main class="flex flex-1 flex-col">
        {{ $slot }}
    </main>

</x-layouts.app>
