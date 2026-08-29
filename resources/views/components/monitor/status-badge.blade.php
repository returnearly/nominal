@props([
    'status',
    'enabled' => true,
])

@php
    $statusValue = $enabled
        ? (is_string($status) ? $status : $status->value)
        : 'disabled';
    $label = $enabled
        ? (is_string($status) ? ucfirst($status) : $status->badgeLabel())
        : 'Disabled';
@endphp

<span data-status="{{ $statusValue }}" {{ $attributes->class('nm-status-badge') }}>
    <span class="nm-status-dot"></span>
    {{ $label }}
</span>
