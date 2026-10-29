<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-100 leading-tight">Statistieken</h2>
    </x-slot>

    @php
        $s = $stats['summary'];
        $weekdays = [1 => 'Maandag', 2 => 'Dinsdag', 3 => 'Woensdag', 4 => 'Donderdag', 5 => 'Vrijdag', 6 => 'Zaterdag', 7 => 'Zondag'];
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-wrap gap-2 text-sm">
                @foreach ($periods as $key => $label)
                    <a href="{{ route('statistics.index', ['period' => $key]) }}" class="px-3 py-1 rounded {{ $period === $key ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-400' }}">{{ $label }}</a>
                @endforeach
                <a href="{{ route('journal.export') }}" class="ms-auto px-3 py-1 rounded bg-slate-800 text-slate-300 hover:text-white">⬇ Export trades (CSV)</a>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <x-card title="Trades"><div class="text-2xl font-semibold text-slate-100">{{ $s['count'] }}</div><div class="text-xs text-slate-500">{{ $s['wins'] }} winst · {{ $s['losses'] }} verlies</div></x-card>
                <x-card title="Win rate"><div class="text-2xl font-semibold text-slate-100">{{ $s['win_rate'] === null ? '—' : $s['win_rate'].'%' }}</div></x-card>
                <x-card title="Profit factor"><div class="text-2xl font-semibold text-slate-100">{{ $s['profit_factor'] ?? '—' }}</div><div class="text-xs text-slate-500">bruto winst / bruto verlies</div></x-card>
                <x-card title="Expectancy"><div class="text-2xl font-semibold">@if ($s['expectancy_cents'] !== null)<x-money :cents="$s['expectancy_cents']" signed colored />@else — @endif</div><div class="text-xs text-slate-500">gemiddeld per trade</div></x-card>
                <x-card title="Gem. winst"><div class="text-2xl font-semibold">@if ($s['avg_win_cents'] !== null)<x-money :cents="$s['avg_win_cents']" signed colored />@else — @endif</div></x-card>
                <x-card title="Gem. verlies"><div class="text-2xl font-semibold">@if ($s['avg_loss_cents'] !== null)<x-money :cents="$s['avg_loss_cents']" signed colored />@else — @endif</div></x-card>
                <x-card title="Beste / slechtste">
                    <div class="text-sm">@if ($s['best']) {{ $s['best']['symbol'] }} <x-money :cents="$s['best']['pnl_cents']" signed colored /> @else — @endif</div>
                    <div class="text-sm">@if ($s['worst']) {{ $s['worst']['symbol'] }} <x-money :cents="$s['worst']['pnl_cents']" signed colored /> @endif</div>
                </x-card>
                <x-card title="Max. drawdown"><div class="text-2xl font-semibold text-red-400"><x-money :cents="$stats['max_drawdown_cents']" /></div><div class="text-xs text-slate-500">{{ $stats['max_drawdown_percent'] }}% van de piek</div></x-card>
            </div>

            <x-card title="Equity curve &amp; drawdown">
                @if (count($stats['equity_curve']) >= 2)
                    <div x-data="equityChart(@js($stats['equity_curve']))"><div x-ref="chart" class="w-full"></div></div>
                    <p class="text-xs text-slate-500 mt-1">Eén punt per dag (slotstand). Open het dashboard of draai <code>trading:snapshot</code> om de laatste stand vast te leggen.</p>
                @else
                    <p class="text-sm text-slate-500 py-8 text-center">Nog te weinig dagen data voor een grafiek.</p>
                @endif
            </x-card>

            <div class="grid lg:grid-cols-3 gap-6">
                @foreach ([
                    'Per tag' => collect($stats['by_tag'])->mapWithKeys(fn ($g, $k) => [$k => $g]),
                    'Per weekdag' => collect($stats['by_weekday'])->mapWithKeys(fn ($g, $k) => [$weekdays[$k] => $g]),
                    'Per uur (ET)' => collect($stats['by_hour'])->mapWithKeys(fn ($g, $k) => [sprintf('%02d:00', $k) => $g]),
                ] as $title => $groups)
                    <x-card :title="$title">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-1"></th><th class="text-right">Trades</th><th class="text-right">Win %</th><th class="text-right">P&amp;L</th></tr></thead>
                            <tbody class="divide-y divide-slate-800">
                                @forelse ($groups as $label => $g)
                                    <tr class="text-slate-300">
                                        <td class="py-1">{{ $label }}</td><td class="text-right">{{ $g['count'] }}</td>
                                        <td class="text-right">{{ $g['win_rate'] }}%</td><td class="text-right"><x-money :cents="$g['pnl_cents']" signed colored /></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-4 text-center text-slate-500">Geen data.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </x-card>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
