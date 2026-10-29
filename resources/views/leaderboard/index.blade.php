<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-100 leading-tight">Leaderboard</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <div class="flex flex-wrap gap-2 text-sm">
                @foreach ($periods as $key => $label)
                    <a href="{{ route('leaderboard.index', ['period' => $key]) }}" class="px-3 py-1 rounded {{ $period === $key ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-400' }}">{{ $label }}</a>
                @endforeach
            </div>

            <x-card>
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500">
                        <tr><th class="py-2 pe-4">#</th><th class="pe-4">Trader</th><th class="pe-4 text-right">Rendement</th><th class="text-right">Gesloten trades</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($ranking as $row)
                            <tr class="{{ $row['user_id'] === auth()->id() ? 'bg-emerald-900/20' : '' }} text-slate-300">
                                <td class="py-2 pe-4 tabular-nums">{{ $row['rank'] }}</td>
                                <td class="pe-4 font-medium">{{ $row['name'] }} @if ($row['user_id'] === auth()->id()) <span class="text-xs text-slate-500">(jij)</span> @endif</td>
                                <td class="pe-4 text-right tabular-nums {{ $row['return_bps'] >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                    {{ $row['return_bps'] > 0 ? '+' : '' }}{{ number_format($row['return_bps'] / 100, 2) }}%
                                </td>
                                <td class="text-right tabular-nums">{{ $row['closed_trades'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-slate-500">Nog niemand op het leaderboard.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>

            @unless (auth()->user()->leaderboard_visible)
                <p class="text-sm text-slate-400">Jij staat hier niet op. Zet "Toon mij op het leaderboard" aan bij <a class="text-emerald-400 hover:underline" href="{{ route('account.edit') }}">Instellingen</a>; alleen je naam en rendement in procenten worden getoond.</p>
            @endunless
        </div>
    </div>
</x-app-layout>
