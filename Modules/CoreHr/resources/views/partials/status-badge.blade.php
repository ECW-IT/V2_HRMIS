@php
    $statusValue = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $statusClass = match ($statusValue) {
        'ACTIVE' => 'bg-green-100 text-green-700',
        'SUSPENDED' => 'bg-yellow-100 text-yellow-700',
        'EXITED' => 'bg-gray-100 text-gray-500',
        default => 'bg-gray-100 text-gray-600',
    };
@endphp
<span class="rounded px-2 py-0.5 text-xs font-medium {{ $statusClass }}">{{ $statusValue }}</span>
