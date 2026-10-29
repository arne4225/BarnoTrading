<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-100 leading-tight">Account</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-card title="Trading-instellingen">
                <dl class="text-sm text-slate-300 grid grid-cols-2 gap-y-2">
                    <dt class="text-slate-500">Startkapitaal</dt><dd><x-money :cents="$user->starting_cash_cents" /></dd>
                    <dt class="text-slate-500">Commissie per order</dt><dd><x-money :cents="config('trading.commission_cents')" /> <span class="text-xs text-slate-500">(TRADING_COMMISSION_CENTS)</span></dd>
                    <dt class="text-slate-500">Slippage</dt><dd>{{ number_format(config('trading.slippage_bps') / 100, 2) }}% <span class="text-xs text-slate-500">(TRADING_SLIPPAGE_BPS)</span></dd>
                    <dt class="text-slate-500">Short-onderpand</dt><dd>{{ config('trading.short_margin_percent') }}% <span class="text-xs text-slate-500">(TRADING_SHORT_MARGIN_PERCENT)</span></dd>
                </dl>
            </x-card>

            <x-card title="Account resetten">
                <p class="text-sm text-slate-400 mb-4">Wist alle orders, posities, transacties en historie en zet je cash terug op het gekozen startkapitaal. Je watchlist blijft bestaan.</p>
                <form method="POST" action="{{ route('account.reset') }}" class="space-y-4" onsubmit="return confirm('Weet je zeker dat je alles wilt wissen?')">
                    @csrf
                    <div>
                        <x-input-label for="starting_cash" value="Nieuw startkapitaal (hele dollars)" />
                        <x-text-input id="starting_cash" name="starting_cash" type="number" min="1000" max="100000000" step="1" class="mt-1 block w-full" :value="old('starting_cash', intdiv($user->starting_cash_cents, 100))" required />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-300">
                        <input type="checkbox" name="confirm" value="1" class="rounded border-slate-700 bg-slate-800 text-emerald-600"> Ja, wis alles
                    </label>
                    <x-danger-button>Reset account</x-danger-button>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
