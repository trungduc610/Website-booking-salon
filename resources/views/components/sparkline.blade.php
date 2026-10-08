@props(['values', 'label'])
@php
    $maximum = max(1, max($values));
    $points = collect($values)
        ->map(fn($value, $i) => ($i * 160) / max(1, count($values) - 1) . ',' . round(38 - ($value / $maximum) * 32, 2))
        ->join(' ');
@endphp
<svg viewBox="0 0 160 44" class="sparkline" role="img" aria-label="{{ $label }}: {{ implode(', ', $values) }}">
    <polyline fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
        points="{{ $points }}" />
</svg>
