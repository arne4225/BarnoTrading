<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-100 leading-tight">Watchlist</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <form method="POST" action="{{ route('watchlist.store') }}" class="flex gap-2">
                @csrf
                <x-text-input name="symbol" placeholder="Symbool toevoegen, bv. NVDA" class="w-full uppercase" required />
                <x-primary-button>Toevoegen</x-primary-button>
            </form>

            <x-card>
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500">
                        <tr><th class="py-2 pe-4">Symbool</th><th class="pe-4 text-right">Koers</th><th class="pe-4">Vertraging</th><th></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($items as $item)
                            @php($quote = $quotes[$item->symbol] ?? null)
                            <tr class="text-slate-300">
                                <td class="py-2 pe-4 font-medium"><a class="text-emerald-400 hover:underline" href="{{ route('stocks.show', $item->symbol) }}">{{ $item->symbol }}</a></td>
                                <td class="pe-4 text-right">@if ($quote) <x-money :cents="$quote->priceCents" /> @else — @endif</td>
                                <td class="pe-4"><x-quote-delay :quote="$quote" /></td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('watchlist.destroy', $item) }}">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-400 hover:text-red-300">Verwijder</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-slate-500">Je watchlist is leeg.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
        </div>
    </div>
</x-app-layout>
