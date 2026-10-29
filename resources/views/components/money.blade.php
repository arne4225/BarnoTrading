@props(['cents', 'signed' => false, 'colored' => false])

@php
    $class = $colored ? ($cents > 0 ? 'text-emerald-400' : ($cents < 0 ? 'text-red-400' : 'text-slate-300')) : '';
@endphp

<span {{ $attributes->class([$class, 'tabular-nums']) }}>{{ \App\Support\Money::format($cents, $signed) }}</span>
