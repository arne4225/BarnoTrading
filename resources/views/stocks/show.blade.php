<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight">
                {{ $symbol }} @if ($name) <span class="text-slate-400 font-normal text-base">— {{ $name }}</span> @endif
            </h2>
            @if ($watchlistItem)
                <form method="POST" action="{{ route('watchlist.destroy', $watchlistItem) }}">
                    @csrf @method('DELETE')
                    <button class="text-sm text-slate-400 hover:text-slate-200">★ In watchlist</button>
                </form>
            @else
                <form method="POST" action="{{ route('watchlist.store') }}">
                    @csrf
                    <input type="hidden" name="symbol" value="{{ $symbol }}">
                    <button class="text-sm text-slate-400 hover:text-slate-200">☆ Watchlist</button>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <x-card>
                    <div x-data="{ price: @js($quote ? \App\Support\Money::format($quote->priceCents) : '—'), delay: @js($quote?->delayLabel()) }"
                         x-init="setInterval(async () => {
                            try {
                                const r = await fetch(@js(route('stocks.quote', $symbol)), { headers: { Accept: 'application/json' } });
                                if (! r.ok) return;
                                const q = await r.json();
                                if (q.price) { price = q.price; delay = q.stale ? 'laatst bekend, ' + q.delay : q.delay; }
                            } catch (e) {}
                         }, 5000)">
                    <div class="flex items-baseline gap-3">
                        <span class="text-3xl font-semibold text-slate-100 tabular-nums" x-text="price"></span>
                        <span class="text-xs text-slate-500" x-text="delay"></span>
                    </div>
                    </div>
                    @if ($quote)
                        <div class="text-xs text-slate-500 mt-1">bid <x-money :cents="$quote->bidCents" /> · ask <x-money :cents="$quote->askCents" /></div>
                    @else
                        <div class="text-sm text-amber-400 mt-1">Geen koers beschikbaar voor {{ $symbol }}.</div>
                    @endif

                    <div class="flex gap-2 mt-4 text-xs">
                        @foreach (\App\Enums\Timeframe::cases() as $tf)
                            <a href="{{ route('stocks.show', [$symbol, 'tf' => $tf->value]) }}"
                               class="px-2 py-1 rounded {{ $tf === $timeframe ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-400 hover:text-slate-200' }}">{{ $tf->label() }}</a>
                        @endforeach
                    </div>
                    <div class="mt-3"><x-price-chart :candles="$candles" /></div>
                </x-card>

                @if ($position)
                    <x-card title="Jouw positie">
                        <div class="text-sm text-slate-300 flex flex-wrap gap-x-8 gap-y-1">
                            <span>{{ $position->isShort() ? 'Short' : 'Long' }} {{ abs($position->quantity) }} aandelen</span>
                            <span>gem. instap <x-money :cents="$position->averagePriceCents()" /></span>
                            @if ($quote)
                                @php($pnl = $position->quantity > 0 ? $position->quantity * $quote->priceCents - $position->cost_basis_cents : $position->cost_basis_cents + $position->quantity * $quote->priceCents)
                                <span>onrealised <x-money :cents="$pnl" signed colored /></span>
                            @endif
                        </div>
                    </x-card>
                @endif

                <x-card :title="'Open orders voor '.$symbol">
                    <x-orders-table :orders="$openOrders" cancellable />
                </x-card>
            </div>

            <x-card title="Order plaatsen">
                <div x-data="{
                    action: @js(old('is_short') ? 'short' : old('side', 'buy')),
                    type: @js(old('type', 'market')),
                    quantity: @js((int) old('quantity', 1)),
                    bracket: @js((bool) (old('take_profit') || old('stop_loss'))),
                    trailMode: 'amount',
                    price: {{ $quote ? $quote->priceCents / 100 : 0 }},
                    get estimate() { return (this.quantity || 0) * this.price; },
                }">
                <form method="POST" action="{{ route('orders.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="symbol" value="{{ $symbol }}">
                    <input type="hidden" name="side" :value="action === 'buy' ? 'buy' : 'sell'">
                    <input type="hidden" name="is_short" :value="action === 'short' ? 1 : 0">

                    <div class="grid grid-cols-3 gap-1 text-sm font-medium">
                        <button type="button" @click="action = 'buy'" :class="action === 'buy' ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-400'" class="rounded py-2">Kopen</button>
                        <button type="button" @click="action = 'sell'" :class="action === 'sell' ? 'bg-red-600 text-white' : 'bg-slate-800 text-slate-400'" class="rounded py-2">Verkopen</button>
                        <button type="button" @click="action = 'short'" :class="action === 'short' ? 'bg-red-800 text-white' : 'bg-slate-800 text-slate-400'" class="rounded py-2">Short</button>
                    </div>
                    <p class="text-xs text-slate-500" x-show="action === 'buy' && {{ $position?->isShort() ? 'true' : 'false' }}">Kopen dekt je short-positie af.</p>
                    <p class="text-xs text-slate-500" x-show="action === 'short'">Short: je verkoopt aandelen die je leent. Onderpand: {{ config('trading.short_margin_percent') }}% van de waarde.</p>

                    <div>
                        <x-input-label for="type" value="Type" />
                        <select id="type" name="type" x-model="type" class="mt-1 block w-full border-slate-700 bg-slate-800 text-slate-100 rounded-md shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            @foreach (\App\Enums\OrderType::cases() as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="quantity" value="Aantal" />
                        <x-text-input id="quantity" name="quantity" type="number" min="1" step="1" x-model.number="quantity" class="mt-1 block w-full" required />
                    </div>

                    <div x-show="type === 'limit' || type === 'stop_limit'" x-cloak>
                        <x-input-label for="limit_price" value="Limietprijs ($)" />
                        <x-text-input id="limit_price" name="limit_price" inputmode="decimal" :value="old('limit_price')" class="mt-1 block w-full" ::disabled="! (type === 'limit' || type === 'stop_limit')" />
                    </div>
                    <div x-show="type === 'stop' || type === 'stop_limit'" x-cloak>
                        <x-input-label for="stop_price" value="Stopprijs ($)" />
                        <x-text-input id="stop_price" name="stop_price" inputmode="decimal" :value="old('stop_price')" class="mt-1 block w-full" ::disabled="! (type === 'stop' || type === 'stop_limit')" />
                    </div>
                    <div x-show="type === 'trailing_stop'" x-cloak class="space-y-2">
                        <div class="flex gap-4 text-sm text-slate-300">
                            <label><input type="radio" value="amount" x-model="trailMode" class="text-emerald-600"> Bedrag ($)</label>
                            <label><input type="radio" value="percent" x-model="trailMode" class="text-emerald-600"> Percentage (%)</label>
                        </div>
                        <x-text-input name="trail_amount" inputmode="decimal" placeholder="Afstand in $" class="block w-full" x-show="trailMode === 'amount'" ::disabled="type !== 'trailing_stop' || trailMode !== 'amount'" />
                        <x-text-input name="trail_percent" inputmode="decimal" placeholder="Afstand in %" class="block w-full" x-show="trailMode === 'percent'" ::disabled="type !== 'trailing_stop' || trailMode !== 'percent'" />
                    </div>

                    <div x-show="type !== 'trailing_stop'" x-cloak>
                        <label class="flex items-center gap-2 text-sm text-slate-300">
                            <input type="checkbox" x-model="bracket" class="rounded border-slate-700 bg-slate-800 text-emerald-600"> Take-profit / stop-loss (bracket)
                        </label>
                        <div x-show="bracket" x-cloak class="mt-2 grid grid-cols-2 gap-2">
                            <div>
                                <x-input-label for="take_profit" value="Take-profit ($)" />
                                <x-text-input id="take_profit" name="take_profit" inputmode="decimal" :value="old('take_profit')" class="mt-1 block w-full" ::disabled="! bracket || type === 'trailing_stop'" />
                            </div>
                            <div>
                                <x-input-label for="stop_loss" value="Stop-loss ($)" />
                                <x-text-input id="stop_loss" name="stop_loss" inputmode="decimal" :value="old('stop_loss')" class="mt-1 block w-full" ::disabled="! bracket || type === 'trailing_stop'" />
                            </div>
                        </div>
                    </div>

                    <div class="text-xs text-slate-500 space-y-1">
                        <div>Geschatte waarde: <span class="text-slate-300 tabular-nums" x-text="'$' + estimate.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span></div>
                        <div>Koopkracht: <x-money :cents="$buyingPowerCents" class="text-slate-300" /></div>
                    </div>

                    <x-primary-button class="w-full justify-center" x-text="action === 'buy' ? 'Koop {{ $symbol }}' : (action === 'sell' ? 'Verkoop {{ $symbol }}' : 'Short {{ $symbol }}')">Plaats order</x-primary-button>
                </form>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
