@props(['values'])

@php
    $count = count($values);
    if ($count > 1) {
        $min = min($values);
        $range = max($values) - $min ?: 1;
        $points = [];
        foreach (array_values($values) as $i => $value) {
            $points[] = round($i / ($count - 1) * 100, 1).','.round(26 - ($value - $min) / $range * 24, 1);
        }
        $color = end($values) >= $values[0] ? '#34d399' : '#f87171';
    }
@endphp

@if ($count > 1)
    <svg viewBox="0 0 100 28" class="h-7 w-24" preserveAspectRatio="none" role="img" aria-label="Koersverloop 30 dagen">
        <polyline points="{{ implode(' ', $points) }}" fill="none" stroke="{{ $color }}" stroke-width="1.5" vector-effect="non-scaling-stroke" />
    </svg>
@endif
