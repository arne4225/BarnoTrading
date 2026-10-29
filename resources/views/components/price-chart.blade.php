@props(['candles'])

@php
    $width = 800;
    $height = 260;
    $closes = array_map(fn ($c) => $c->closeCents, $candles);
    $count = count($closes);
    if ($count > 1) {
        $min = min($closes);
        $max = max($closes);
        $range = max(1, $max - $min);
        $points = [];
        foreach ($closes as $i => $close) {
            $points[] = round($i / ($count - 1) * $width, 1).','.round($height - 20 - ($close - $min) / $range * ($height - 40), 1);
        }
        $up = end($closes) >= $closes[0];
        $color = $up ? '#34d399' : '#f87171';
        $line = implode(' ', $points);
    }
@endphp

@if ($count > 1)
    <div>
        <svg viewBox="0 0 {{ $width }} {{ $height }}" class="w-full h-64" preserveAspectRatio="none" role="img" aria-label="Koersgrafiek">
            <polygon points="0,{{ $height }} {{ $line }} {{ $width }},{{ $height }}" fill="{{ $color }}" opacity="0.12" />
            <polyline points="{{ $line }}" fill="none" stroke="{{ $color }}" stroke-width="2" vector-effect="non-scaling-stroke" />
        </svg>
        <div class="flex justify-between text-xs text-slate-500 mt-1">
            <span>{{ $candles[0]->time->timezone(config('trading.timezone'))->format('d M H:i') }} ET</span>
            <span>laag <x-money :cents="$min" /> · hoog <x-money :cents="$max" /></span>
            <span>{{ end($candles)->time->timezone(config('trading.timezone'))->format('d M H:i') }} ET</span>
        </div>
    </div>
@else
    <p class="text-slate-500 text-sm py-12 text-center">Geen grafiekdata beschikbaar.</p>
@endif
