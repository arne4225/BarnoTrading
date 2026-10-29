@props(['quote'])

@if ($quote)
    <span {{ $attributes->class(['text-xs', $quote->isStale ? 'text-amber-400' : 'text-slate-500']) }}>
        {{ $quote->isStale ? 'laatst bekend, ' : '' }}{{ $quote->delayLabel() }}
    </span>
@endif
