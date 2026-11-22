@props(['units'])

@foreach($units as $unit)
    <option
        value="{{ $unit['id'] }}"
        @if(($unit['type'] ?? '') === 'DEPARTMENT')
            style="background-color: var(--color-erp-accent-soft); font-weight: 600; color: var(--color-erp-navbar);"
        @endif
    >{{ $unit['label'] }}</option>
@endforeach
