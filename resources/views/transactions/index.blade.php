<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-100 leading-tight">Transacties</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-2 gap-4">
                <x-card title="Gerealiseerde P&L"><div class="text-2xl font-semibold"><x-money :cents="$realizedPnlCents" signed colored /></div></x-card>
                <x-card title="Betaalde commissie"><div class="text-2xl font-semibold text-slate-100"><x-money :cents="$commissionCents" /></div></x-card>
            </div>

            <x-card>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="py-2 pe-4">Tijd (ET)</th><th class="pe-4">Symbool</th><th class="pe-4">Zijde</th>
                                <th class="pe-4 text-right">Aantal</th><th class="pe-4 text-right">Prijs</th>
                                <th class="pe-4 text-right">Commissie</th><th class="text-right">Gerealiseerd</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @forelse ($transactions as $t)
                                <tr class="text-slate-300">
                                    <td class="py-2 pe-4 whitespace-nowrap text-xs text-slate-500">{{ $t->executed_at->timezone(config('trading.timezone'))->format('d M H:i:s') }}</td>
                                    <td class="pe-4 font-medium">{{ $t->symbol }}</td>
                                    <td class="pe-4 {{ $t->side->value === 'buy' ? 'text-emerald-400' : 'text-red-400' }}">{{ $t->is_short ? 'Short' : $t->side->label() }}</td>
                                    <td class="pe-4 text-right tabular-nums">{{ $t->quantity }}</td>
                                    <td class="pe-4 text-right"><x-money :cents="$t->price_cents" /></td>
                                    <td class="pe-4 text-right"><x-money :cents="$t->commission_cents" /></td>
                                    <td class="text-right"><x-money :cents="$t->realized_pnl_cents" signed colored /></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="py-6 text-center text-slate-500">Nog geen transacties.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $transactions->links() }}</div>
            </x-card>
        </div>
    </div>
</x-app-layout>
