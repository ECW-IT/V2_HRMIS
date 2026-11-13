@props(['showNav' => true, 'lightNav' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo-icon.png') }}" type="image/png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-slate-50 font-sans antialiased">

    @if($showNav)
        {{-- TOP NAVBAR --}}
        <header class="fixed inset-x-0 top-0 z-50 flex h-[var(--spacing-navbar)] items-center gap-2 px-3 {{ $lightNav ? 'border-b bg-white text-gray-700 shadow-sm' : 'bg-[var(--color-erp-navbar)] text-white' }}">

            {{-- Logo --}}
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2 rounded px-2 py-1 text-sm font-bold tracking-tight {{ $lightNav ? 'text-gray-800 hover:bg-gray-100' : 'text-white hover:bg-white/10' }}">
                <img src="{{ asset('images/logo-icon.png') }}" alt="" class="h-5 w-5 rounded-sm object-contain">
                ECW HRMIS
            </a>

            {{-- Module Switcher --}}
            <div class="relative" x-data="{ open: false }">
                <button
                    @click="open = !open"
                    @click.outside="open = false"
                    class="rounded p-1.5 {{ $lightNav ? 'hover:bg-gray-100' : 'hover:bg-white/10' }}"
                    title="Modules"
                >
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                    </svg>
                </button>

                <div
                    x-show="open"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute left-0 top-full mt-1 z-50 w-72 popup border bg-white p-3 shadow-xl"
                    style="display: none;"
                >
                    <p class="mb-2 px-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Modules</p>
                    <div @click="open = false">
                        <x-module-launcher compact />
                    </div>
                </div>
            </div>

            {{-- Module menu (injected by module layout) --}}
            @isset($moduleMenu)
                <div class="mx-1 h-4 w-px {{ $lightNav ? 'bg-gray-200' : 'bg-white/20' }}"></div>
                {{ $moduleMenu }}
            @endisset

            {{-- Spacer --}}
            <div class="flex-1"></div>

            @auth
                @can('approval.act')
                    @php
                        $waitingCount = app(\Modules\Workflow\Services\ApprovalService::class)->pendingFor(auth()->user())->count();
                    @endphp
                    <a
                        href="{{ route('approvals.index') }}"
                        class="relative mr-1 rounded px-2 py-1 text-xs font-medium {{ $lightNav ? 'hover:bg-gray-100' : 'hover:bg-white/10' }}"
                    >
                        Approvals
                        @if ($waitingCount > 0)
                            <span class="ml-1 inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                                {{ $waitingCount }}
                            </span>
                        @endif
                    </a>
                @endcan
                <livewire:company-selector />
            @endauth

            {{-- User Avatar --}}
            <div class="relative" x-data="{ open: false }">
                <button
                    @click="open = !open"
                    @click.outside="open = false"
                    class="flex h-7 w-7 items-center justify-center rounded-full bg-[var(--color-erp-accent)] text-xs font-semibold text-white hover:bg-[var(--color-erp-cyan)]"
                >
                    {{ auth()->check() ? auth()->user()->initials() : 'U' }}
                </button>

                <div
                    x-show="open"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute right-0 top-full mt-1 z-50 w-44 popup border bg-white py-1 shadow-lg text-sm text-gray-700"
                    style="display: none;"
                >
                    @auth
                        <div class="border-b px-3 py-2">
                            <p class="truncate text-xs font-medium text-gray-800">{{ auth()->user()->name }}</p>
                            <p class="truncate text-[11px] text-gray-400">{{ auth()->user()->email }}</p>
                        </div>
                    @endauth
                    <a href="#" class="block px-3 py-1.5 hover:bg-gray-50">Profile</a>
                    <a href="#" class="block px-3 py-1.5 hover:bg-gray-50">Settings</a>
                    <div class="border-t"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full px-3 py-1.5 text-left text-xs text-red-600 hover:bg-red-50">
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </header>
    @endif

    {{-- Page body: pushed below navbar only when nav is visible --}}
    <div class="{{ $showNav ? 'pt-[var(--spacing-navbar)]' : '' }} flex h-full flex-col">
        <div class="min-h-0 flex-1">
            {{ $slot }}
        </div>
    </div>

    @livewireScripts
</body>
</html>
