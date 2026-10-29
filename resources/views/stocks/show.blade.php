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

                    <div class="mt-4"
                         x-data="stockChart({
                            url: @js(route('stocks.chart', $symbol)),
                            timeframe: @js($timeframe->value),
                            timeframes: @js(collect(\App\Enums\Timeframe::cases())->map(fn ($tf) => ['value' => $tf->value, 'label' => $tf->label()])),
                            isShort: @js((bool) $position?->isShort()),
                         })">
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <template x-for="tf in timeframes" :key="tf.value">
                                <button type="button" @click="setTimeframe(tf.value)" x-text="tf.label"
                                        :class="timeframe === tf.value ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-400 hover:text-slate-200'"
                                        class="px-2 py-1 rounded"></button>
                            </template>
                            <span class="ms-auto flex gap-2">
                                <button type="button" @click="levels.stop_loss === null ? addLevel('stop_loss') : removeLevel('stop_loss')"
                                        :class="levels.stop_loss !== null ? 'bg-red-900/60 text-red-200' : 'bg-slate-800 text-slate-400'" class="px-2 py-1 rounded">Stop-loss lijn</button>
                                <button type="button" @click="levels.take_profit === null ? addLevel('take_profit') : removeLevel('take_profit')"
                                        :class="levels.take_profit !== null ? 'bg-emerald-900/60 text-emerald-200' : 'bg-slate-800 text-slate-400'" class="px-2 py-1 rounded">Take-profit lijn</button>
                            </span>
                        </div>

                        <div class="flex flex-wrap gap-x-5 gap-y-2 mt-3 text-xs text-slate-300">
                            <label class="flex items-center gap-1"><input type="checkbox" x-model="indicators.volume.on" @change="toggle()" class="rounded border-slate-700 bg-slate-800 text-emerald-600"> Volume</label>
                            <label class="flex items-center gap-1"><input type="checkbox" x-model="indicators.vwap.on" @change="toggle()" class="rounded border-slate-700 bg-slate-800 text-emerald-600"> VWAP</label>
                            <label class="flex items-center gap-1"><input type="checkbox" x-model="indicators.sma.on" @change="toggle()" class="rounded border-slate-700 bg-slate-800 text-emerald-600"> SMA
                                <input type="text" x-model="indicators.sma.periods" @change="toggle()" class="w-20 px-1 py-0 text-xs rounded border-slate-700 bg-slate-800"></label>
                            <label class="flex items-center gap-1"><input type="checkbox" x-model="indicators.ema.on" @change="toggle()" class="rounded border-slate-700 bg-slate-800 text-emerald-600"> EMA
                                <input type="text" x-model="indicators.ema.periods" @change="toggle()" class="w-20 px-1 py-0 text-xs rounded border-slate-700 bg-slate-800"></label>
                            <label class="flex items-center gap-1"><input type="checkbox" x-model="indicators.rsi.on" @change="toggle()" class="rounded border-slate-700 bg-slate-800 text-emerald-600"> RSI
                                <input type="number" min="2" max="200" x-model.number="indicators.rsi.period" @change="toggle()" class="w-14 px-1 py-0 text-xs rounded border-slate-700 bg-slate-800"></label>
                            <label class="flex items-center gap-1"><input type="checkbox" x-model="indicators.macd.on" @change="toggle()" class="rounded border-slate-700 bg-slate-800 text-emerald-600"> MACD
                                <input type="text" x-model="indicators.macd.params" @change="toggle()" class="w-20 px-1 py-0 text-xs rounded border-slate-700 bg-slate-800"></label>
                        </div>

                        <p x-show="error" x-text="error" x-cloak class="mt-2 text-xs text-amber-400"></p>
                        <div x-ref="chart" class="mt-3 w-full"></div>
                        <p class="text-xs text-slate-500 mt-1">Tijden in Eastern Time. Sleep de SL/TP-lijnen op de grafiek; de niveaus worden in het orderformulier ingevuld.</p>
                    </div>
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

                <x-card title="Prijsalert">
                    <form method="POST" action="{{ route('alerts.store') }}" class="flex gap-2">
                        @csrf
                        <input type="hidden" name="symbol" value="{{ $symbol }}">
                        <x-text-input name="price" inputmode="decimal" placeholder="Meld me bij niveau ($)" class="w-full" required />
                        <x-primary-button>Alert zetten</x-primary-button>
                    </form>
                </x-card>

                <x-card :title="'Open orders voor '.$symbol">
                    <x-orders-table :orders="$openOrders" cancellable />
                </x-card>
            </div>

            <x-card title="Order plaatsen">
                <div @chart-levels.window="stopLoss = $event.detail.stop_loss ?? ''; takeProfit = $event.detail.take_profit ?? ''; bracket = bracket || stopLoss !== '' || takeProfit !== ''; schedulePreview()"
                     x-data="{
                    limitPrice: @js(old('limit_price', '')),
                    stopPrice: @js(old('stop_price', '')),
                    riskPercent: @js(number_format(auth()->user()->risk_per_trade_bps / 100, 2, '.', '')),
                    sizing: null,
                    warnings: [],
                    riskError: null,
                    previewTimer: null,
                    schedulePreview() {
                        clearTimeout(this.previewTimer);
                        this.previewTimer = setTimeout(() => this.preview(false), 400);
                    },
                    async preview(fillQuantity) {
                        const params = new URLSearchParams({
                            symbol: @js($symbol),
                            side: this.action === 'buy' ? 'buy' : 'sell',
                            is_short: this.action === 'short' ? 1 : 0,
                            risk_percent: this.riskPercent,
                        });
                        const entry = this.type === 'limit' || this.type === 'stop_limit' ? this.limitPrice : (this.type === 'stop' ? this.stopPrice : '');
                        if (entry) params.set('entry', entry);
                        if (this.stopLoss) params.set('stop_loss', this.stopLoss);
                        if (this.takeProfit) params.set('take_profit', this.takeProfit);
                        if (this.quantity > 0 && !fillQuantity) params.set('quantity', this.quantity);
                        try {
                            const r = await fetch(@js(route('risk.preview')) + '?' + params, { headers: { Accept: 'application/json' } });
                            const d = await r.json();
                            this.riskError = d.error ?? null;
                            this.sizing = d.sizing ?? null;
                            this.warnings = d.warnings ?? [];
                            if (fillQuantity && d.sizing && d.sizing.shares > 0) {
                                this.quantity = d.sizing.shares;
                                this.schedulePreview();
                            }
                        } catch (e) {}
                    },
                    stopLoss: @js(old('stop_loss', '')),
                    takeProfit: @js(old('take_profit', '')),
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
                        <x-text-input id="quantity" name="quantity" type="number" min="1" step="1" x-model.number="quantity" @input="schedulePreview()" class="mt-1 block w-full" required />
                    </div>

                    <div x-show="type === 'limit' || type === 'stop_limit'" x-cloak>
                        <x-input-label for="limit_price" value="Limietprijs ($)" />
                        <x-text-input id="limit_price" name="limit_price" inputmode="decimal" x-model="limitPrice" class="mt-1 block w-full" ::disabled="! (type === 'limit' || type === 'stop_limit')" />
                    </div>
                    <div x-show="type === 'stop' || type === 'stop_limit'" x-cloak>
                        <x-input-label for="stop_price" value="Stopprijs ($)" />
                        <x-text-input id="stop_price" name="stop_price" inputmode="decimal" x-model="stopPrice" class="mt-1 block w-full" ::disabled="! (type === 'stop' || type === 'stop_limit')" />
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
                                <x-text-input id="take_profit" name="take_profit" inputmode="decimal" x-model="takeProfit" @input="schedulePreview()" class="mt-1 block w-full" ::disabled="! bracket || type === 'trailing_stop'" />
                            </div>
                            <div>
                                <x-input-label for="stop_loss" value="Stop-loss ($)" />
                                <x-text-input id="stop_loss" name="stop_loss" inputmode="decimal" x-model="stopLoss" @input="schedulePreview()" class="mt-1 block w-full" ::disabled="! bracket || type === 'trailing_stop'" />
                            </div>
                        </div>
                    </div>

                    <div class="rounded-md border border-slate-800 p-3 space-y-2" x-show="type !== 'trailing_stop'" x-cloak>
                        <div class="text-xs font-semibold uppercase text-slate-400">Risico &amp; positiegrootte</div>
                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <x-input-label for="risk_percent" value="Risico (% van account)" />
                                <x-text-input id="risk_percent" inputmode="decimal" x-model="riskPercent" @input="schedulePreview()" class="mt-1 block w-full" />
                            </div>
                            <button type="button" @click="preview(true)" :disabled="!stopLoss" class="rounded bg-slate-700 px-3 py-2 text-xs text-slate-100 hover:bg-slate-600 disabled:opacity-40">Vul aantal in</button>
                        </div>
                        <p class="text-xs text-slate-500" x-show="!stopLoss">Vul een stop-loss in (of sleep de lijn op de grafiek) om het aantal te berekenen.</p>
                        <p class="text-xs text-red-400" x-show="riskError" x-text="riskError"></p>
                        <dl class="text-xs text-slate-300 grid grid-cols-2 gap-x-3 gap-y-1" x-show="sizing" x-cloak>
                            <dt class="text-slate-500">Aantal</dt><dd class="tabular-nums" x-text="sizing?.shares + (sizing?.capped_by_buying_power ? ' (max koopkracht)' : '')"></dd>
                            <dt class="text-slate-500">Bedrag</dt><dd class="tabular-nums" x-text="'$' + sizing?.position_value.toLocaleString('en-US', {minimumFractionDigits: 2})"></dd>
                            <dt class="text-slate-500">Max. verlies</dt><dd class="tabular-nums text-red-400" x-text="'-$' + sizing?.max_loss.toLocaleString('en-US', {minimumFractionDigits: 2})"></dd>
                            <dt class="text-slate-500">Potentiële winst</dt><dd class="tabular-nums text-emerald-400" x-text="sizing?.potential_profit === null ? '—' : '+$' + sizing?.potential_profit.toLocaleString('en-US', {minimumFractionDigits: 2})"></dd>
                            <dt class="text-slate-500">Risk/reward</dt><dd class="tabular-nums" x-text="sizing?.risk_reward === null ? '—' : '1:' + sizing?.risk_reward"></dd>
                        </dl>
                        <ul class="text-xs text-amber-300 space-y-1" x-show="warnings.length" x-cloak>
                            <template x-for="warning in warnings" :key="warning"><li x-text="'⚠ ' + warning"></li></template>
                        </ul>
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
