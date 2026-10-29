<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-100 leading-tight">Dashboard</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <x-card title="Portfoliowaarde">
                    <div class="text-2xl font-semibold text-slate-100"><x-money :cents="$summary->equityCents" /></div>
                    <div class="text-xs text-slate-500">totaal <x-money :cents="$summary->totalPnlCents" signed colored /></div>
                </x-card>
                <x-card title="Dagelijkse P&L">
                    <div class="text-2xl font-semibold"><x-money :cents="$summary->dailyPnlCents" signed colored /></div>
                    <div class="text-xs text-slate-500">t.o.v. vorige slotstand</div>
                </x-card>
                <x-card title="Cash">
                    <div class="text-2xl font-semibold text-slate-100"><x-money :cents="$summary->cashCents" /></div>
                    <div class="text-xs text-slate-500">koopkracht <x-money :cents="$summary->buyingPowerCents" /></div>
                </x-card>
                <x-card title="Onrealised P&L">
                    <div class="text-2xl font-semibold"><x-money :cents="$summary->unrealizedPnlCents" signed colored /></div>
                    <div class="text-xs text-slate-500">{{ count($summary->positions) }} open positie(s)</div>
                </x-card>
            </div>

            <x-card title="Open posities">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="py-2 pe-4">Symbool</th><th class="pe-4 text-right">Aantal</th>
                                <th class="pe-4 text-right">Gem. instap</th><th class="pe-4 text-right">Koers</th>
                                <th class="pe-4 text-right">Waarde</th><th class="text-right">Onrealised P&L</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @forelse ($summary->positions as $view)
                                <tr class="text-slate-300">
                                    <td class="py-2 pe-4 font-medium">
                                        <a class="text-emerald-400 hover:underline" href="{{ route('stocks.show', $view->position->symbol) }}">{{ $view->position->symbol }}</a>
                                        @if ($view->position->isShort()) <span class="text-xs text-red-400">short</span> @endif
                                    </td>
                                    <td class="pe-4 text-right tabular-nums">{{ $view->position->quantity }}</td>
                                    <td class="pe-4 text-right"><x-money :cents="$view->position->averagePriceCents()" /></td>
                                    <td class="pe-4 text-right">
                                        <x-money :cents="$view->priceCents" />
                                        <div><x-quote-delay :quote="$view->quote" /></div>
                                    </td>
                                    <td class="pe-4 text-right"><x-money :cents="$view->marketValueCents" /></td>
                                    <td class="text-right">
                                        <x-money :cents="$view->unrealizedPnlCents" signed colored />
                                        <span class="text-xs text-slate-500">({{ number_format($view->unrealizedPnlPercent, 2) }}%)</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-6 text-center text-slate-500">Nog geen posities. <a class="text-emerald-400 hover:underline" href="{{ route('stocks.index') }}">Zoek een aandeel</a> om te beginnen.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            <x-card title="Open orders">
                <x-orders-table :orders="$openOrders" cancellable />
            </x-card>
        </div>
    </div>
</x-app-layout>
