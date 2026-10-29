<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-100 leading-tight">Aandelen zoeken</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <form method="GET" action="{{ route('stocks.index') }}" class="flex gap-2">
                <x-text-input name="q" :value="$query" placeholder="Symbool of bedrijfsnaam, bv. AAPL of Tesla" class="w-full" autofocus />
                <x-primary-button>Zoek</x-primary-button>
            </form>

            @if ($directSymbol)
                <p class="text-sm text-slate-400">
                    Niet in de lijst? <a class="text-emerald-400 hover:underline" href="{{ route('stocks.show', $directSymbol) }}">Open {{ $directSymbol }} direct</a>.
                </p>
            @endif

            <x-card :title="$query === '' ? 'Populair' : 'Resultaten'">
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($names as $symbol => $name)
                            <tr class="text-slate-300">
                                <td class="py-2 pe-4 font-medium"><a class="text-emerald-400 hover:underline" href="{{ route('stocks.show', $symbol) }}">{{ $symbol }}</a></td>
                                <td class="pe-4">{{ $name }}</td>
                                <td class="text-right">
                                    @if (isset($quotes[$symbol]))
                                        <x-money :cents="$quotes[$symbol]->priceCents" />
                                        <div><x-quote-delay :quote="$quotes[$symbol]" /></div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="py-6 text-center text-slate-500">Geen resultaten.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
        </div>
    </div>
</x-app-layout>
