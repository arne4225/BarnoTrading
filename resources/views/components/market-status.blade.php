<div class="border-b border-slate-800 bg-slate-900/60 text-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex flex-wrap items-center gap-x-4 gap-y-1">
        <span class="inline-flex items-center gap-2 font-medium {{ $clock->isOpen ? 'text-emerald-400' : 'text-red-400' }}">
            <span class="h-2 w-2 rounded-full {{ $clock->isOpen ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
            Markt {{ $clock->isOpen ? 'open' : 'gesloten' }}
        </span>
        <span class="text-slate-400">
            @if ($clock->isOpen)
                sluit {{ $clock->nextClose->timezone(config('trading.timezone'))->format('H:i') }} ET
            @else
                opent {{ $clock->nextOpen->timezone(config('trading.timezone'))->format('D d M H:i') }} ET — open orders blijven pending tot de opening
            @endif
        </span>
        <span class="text-slate-500 sm:ms-auto">
            @if ($isFake())
                Gesimuleerde koersen (fake provider)
            @else
                Alpaca {{ strtoupper(config('trading.alpaca.feed')) }}-feed — koersen kunnen vertraagd zijn
            @endif
        </span>
    </div>
    @if ($marketData->error())
        <div class="bg-amber-900/40 border-t border-amber-700 text-amber-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2">⚠ Marktdata: {{ $marketData->error() }}</div>
        </div>
    @endif
</div>
