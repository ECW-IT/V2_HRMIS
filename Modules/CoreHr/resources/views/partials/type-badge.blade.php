@php
    $typeValue = $type instanceof \BackedEnum ? $type->value : (string) $type;
    $typeClass = match ($typeValue) {
        'PERMANENT' => 'bg-blue-100 text-blue-700',
        'PROBATION' => 'bg-yellow-100 text-yellow-700',
        'INTERN' => 'bg-purple-100 text-purple-700',
        'CONTRACT' => 'bg-gray-100 text-gray-700',
        default => 'bg-gray-100 text-gray-600',
    };
@endphp
<span class="rounded px-2 py-0.5 text-xs font-medium {{ $typeClass }}">{{ $typeValue }}</span>
