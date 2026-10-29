@foreach ($alerts as $alert)
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-3">
        <div class="flex items-center gap-3 rounded-md bg-sky-900/40 border border-sky-700 text-sky-200 px-4 py-2 text-sm">
            <span>🔔 <a class="font-semibold hover:underline" href="{{ route('stocks.show', $alert->symbol) }}">{{ $alert->symbol }}</a>
                raakte {{ \App\Support\Money::format($alert->price_cents) }}
                ({{ $alert->direction === 'above' ? 'boven' : 'onder' }}); koers nu {{ \App\Support\Money::format($alert->triggered_price_cents) }}.</span>
            <form method="POST" action="{{ route('alerts.acknowledge', $alert) }}" class="ms-auto">
                @csrf
                <button class="text-xs text-sky-300 hover:text-white">Sluiten</button>
            </form>
        </div>
    </div>
@endforeach
