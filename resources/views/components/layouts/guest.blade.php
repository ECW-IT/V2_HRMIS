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
<body class="min-h-full bg-gradient-to-b from-slate-100 via-slate-50 to-[var(--color-erp-accent-soft)] font-sans antialiased">
    <div class="flex min-h-screen flex-col">
        <main class="flex flex-1 items-center justify-center px-4 py-10">
            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>
