@props([
    'variant' => 'edit',
    'label' => null,
    'href' => null,
])

@php
    $defaultLabels = [
        'add' => 'Add',
        'edit' => 'Edit',
        'view' => 'View',
        'move' => 'Move',
        'deactivate' => 'Deactivate',
        'activate' => 'Activate',
        'delete' => 'Delete',
        'remove' => 'Remove',
        'cancel' => 'Cancel',
        'end' => 'End',
        'permissions' => 'Permissions',
    ];

    $label ??= $defaultLabels[$variant] ?? 'Action';

    $colorClass = match ($variant) {
        'add', 'activate' => 'border-green-600 text-green-600',
        'edit', 'view' => 'border-sky-500 text-sky-500',
        'move' => 'border-slate-900 text-slate-900',
        'permissions' => 'border-[var(--color-erp-accent)] text-[var(--color-erp-accent)]',
        default => 'border-red-600 text-red-600',
    };

    $classes = [
        'inline-flex h-6 w-7 items-center justify-center rounded-md border bg-white/70 shadow-md backdrop-blur-sm hover:bg-white/90',
        $colorClass,
    ];
@endphp

@if ($href)
    <a
        href="{{ $href }}"
        title="{{ $label }}"
        aria-label="{{ $label }}"
        {{ $attributes->class($classes) }}
    >
@else
    <button
        type="button"
        title="{{ $label }}"
        aria-label="{{ $label }}"
        {{ $attributes->class($classes) }}
    >
@endif
    @switch($variant)
        @case('add')
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            @break
        @case('edit')
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125L16.875 4.5" />
            </svg>
            @break
        @case('view')
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            @break
        @case('move')
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 3M21 7.5H7.5" />
            </svg>
            @break
        @case('activate')
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
            @break
        @case('permissions')
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
            </svg>
            @break
        @default
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
    @endswitch
@if ($href)
    </a>
@else
    </button>
@endif
