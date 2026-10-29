<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-100 leading-tight">Watchlist</h2>
    </x-slot>

    @php
        $sortLink = fn (string $column) => route('watchlist.index', [
            'list' => $current->id, 'sort' => $column,
            'dir' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
        ]);
        $arrow = fn (string $column) => $sort === $column ? ($direction === 'asc' ? '▲' : '▼') : '';
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                @foreach ($lists as $list)
                    <a href="{{ route('watchlist.index', ['list' => $list->id]) }}" class="px-3 py-1 rounded {{ $list->is($current) ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-400 hover:text-slate-200' }}">{{ $list->name }}</a>
                @endforeach
                <form method="POST" action="{{ route('watchlists.store') }}" class="flex gap-1 ms-2">
                    @csrf
                    <x-text-input name="name" placeholder="Nieuwe lijst" class="!py-1 text-sm w-36" required />
                    <button class="px-3 py-1 rounded bg-slate-800 text-slate-300 hover:text-white">+</button>
                </form>
                @if ($lists->count() > 1)
                    <form method="POST" action="{{ route('watchlists.destroy', $current) }}" class="ms-auto" onsubmit="return confirm('Lijst verwijderen?')">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-400 hover:text-red-300">Verwijder deze lijst</button>
                    </form>
                @endif
            </div>

            <form method="POST" action="{{ route('watchlist.store') }}" class="flex gap-2">
                @csrf
                <input type="hidden" name="watchlist_id" value="{{ $current->id }}">
                <x-text-input name="symbol" placeholder="Symbool toevoegen aan '{{ $current->name }}', bv. NVDA" class="w-full uppercase" required />
                <x-primary-button>Toevoegen</x-primary-button>
            </form>

            <x-card>
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pe-4"><a href="{{ $sortLink('symbol') }}" class="hover:text-slate-300">Symbool {{ $arrow('symbol') }}</a></th>
                            <th class="pe-4 text-right"><a href="{{ $sortLink('price') }}" class="hover:text-slate-300">Koers {{ $arrow('price') }}</a></th>
                            <th class="pe-4 text-right"><a href="{{ $sortLink('change') }}" class="hover:text-slate-300">Dag % {{ $arrow('change') }}</a></th>
                            <th class="pe-4">30 dagen</th><th class="pe-4">Vertraging</th><th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($rows as $row)
                            <tr class="text-slate-300">
                                <td class="py-2 pe-4 font-medium"><a class="text-emerald-400 hover:underline" href="{{ route('stocks.show', $row['item']->symbol) }}">{{ $row['item']->symbol }}</a></td>
                                <td class="pe-4 text-right">@if ($row['quote']) <x-money :cents="$row['quote']->priceCents" /> @else — @endif</td>
                                <td class="pe-4 text-right tabular-nums {{ ($row['change_percent'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                    {{ $row['change_percent'] === null ? '—' : ($row['change_percent'] > 0 ? '+' : '').number_format($row['change_percent'], 2).'%' }}
                                </td>
                                <td class="pe-4"><x-sparkline :values="$row['sparkline']" /></td>
                                <td class="pe-4"><x-quote-delay :quote="$row['quote']" /></td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('watchlist.destroy', $row['item']) }}">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-400 hover:text-red-300">Verwijder</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-slate-500">Deze lijst is leeg.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>

            <x-card title="Prijsalerts">
                <form method="POST" action="{{ route('alerts.store') }}" class="flex flex-wrap gap-2 mb-4">
                    @csrf
                    <x-text-input name="symbol" placeholder="Symbool" class="w-32 uppercase" required />
                    <x-text-input name="price" inputmode="decimal" placeholder="Niveau in $" class="w-36" required />
                    <x-primary-button>Alert zetten</x-primary-button>
                </form>
                <ul class="divide-y divide-slate-800 text-sm">
                    @forelse ($alerts as $alert)
                        <li class="py-2 flex items-center gap-4 text-slate-300">
                            <a class="font-medium text-emerald-400 hover:underline" href="{{ route('stocks.show', $alert->symbol) }}">{{ $alert->symbol }}</a>
                            <span>{{ $alert->direction === 'above' ? 'boven' : 'onder' }} <x-money :cents="$alert->price_cents" /></span>
                            <form method="POST" action="{{ route('alerts.destroy', $alert) }}" class="ms-auto">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-400 hover:text-red-300">Verwijder</button>
                            </form>
                        </li>
                    @empty
                        <li class="py-3 text-slate-500">Geen actieve alerts. Je krijgt een melding bovenaan de pagina zodra een koers het niveau raakt.</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
</x-app-layout>
