@props([
    'compact' => false,
])

@php
    $modules = [
        ['icon' => '🔐', 'label' => 'Access control', 'route' => 'access-control.users.index'],
        ['icon' => '🏢', 'label' => 'Organisation', 'route' => 'org.locations.index'],
        ['icon' => '👥', 'label' => 'Employees', 'route' => 'employees.index'],
        ['icon' => '🪪', 'label' => 'Attendance', 'route' => null],
        ['icon' => '📅', 'label' => 'Leave', 'route' => null],
        ['icon' => '💰', 'label' => 'Payroll', 'route' => null],
        ['icon' => '✅', 'label' => 'Approvals', 'route' => 'approvals.index'],
        ['icon' => '📊', 'label' => 'Reports', 'route' => null],
        ['icon' => '📜', 'label' => 'Audit', 'route' => null],
        ['icon' => '⚙️', 'label' => 'Settings', 'route' => null],
    ];

    $gridClasses = $compact ? 'grid grid-cols-3 gap-3' : 'grid grid-cols-2 justify-items-start gap-x-6 gap-y-10 sm:grid-cols-4 md:grid-cols-5';
    $itemClasses = $compact ? 'group flex flex-col items-center rounded-lg p-1 text-center' : 'group flex w-full flex-col items-center text-center';
    $iconClasses = $compact
        ? 'flex h-12 w-12 items-center justify-center rounded-lg border text-xl shadow-sm transition-all duration-150'
        : 'flex h-[60px] w-[60px] items-center justify-center rounded-lg border text-2xl shadow-sm transition-all duration-150';
    $labelClasses = $compact ? 'text-center text-[10px] font-medium leading-tight' : 'text-center text-[11px] font-medium leading-tight';
@endphp

<div class="{{ $gridClasses }}">
    @foreach($modules as $module)
        @php
            $active = filled($module['route']) && \Illuminate\Support\Facades\Route::has($module['route']);
        @endphp
        <div class="{{ $itemClasses }}">
            <a
                href="{{ $active ? route($module['route']) : '#' }}"
                @class(['flex flex-col items-center gap-1.5', 'pointer-events-none' => ! $active])
            >
                <div @class([
                    $iconClasses,
                    'border-[var(--color-erp-accent)]/30 bg-[var(--color-erp-accent-soft)] group-hover:-translate-y-0.5 group-hover:shadow-md' => $active,
                    'border-gray-100 bg-gray-50 opacity-40 grayscale' => ! $active,
                ])>
                    {{ $module['icon'] }}
                </div>
                <span @class([
                    $labelClasses,
                    'text-slate-700 group-hover:text-[var(--color-erp-accent)]' => $active,
                    'text-gray-400' => ! $active,
                ])>
                    {{ $module['label'] }}
                </span>
            </a>
        </div>
    @endforeach
</div>
