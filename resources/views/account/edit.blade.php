<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-100 leading-tight">Account</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-card title="Instellingen">
                <form method="POST" action="{{ route('account.update') }}" class="space-y-4">
                    @csrf @method('PUT')
                    <p class="text-sm text-slate-400">Startkapitaal: <x-money :cents="$user->starting_cash_cents" class="text-slate-200" /> (wijzig dit via "Account resetten" hieronder).</p>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="commission" value="Commissie per order ($)" />
                            <x-text-input id="commission" name="commission" inputmode="decimal" class="mt-1 block w-full" :value="old('commission', $user->commission_cents === null ? '' : number_format($user->commission_cents / 100, 2, '.', ''))" :placeholder="'Standaard: '.number_format(config('trading.commission_cents') / 100, 2)" />
                        </div>
                        <div>
                            <x-input-label for="slippage_percent" value="Slippage (%)" />
                            <x-text-input id="slippage_percent" name="slippage_percent" inputmode="decimal" class="mt-1 block w-full" :value="old('slippage_percent', $user->slippage_bps === null ? '' : number_format($user->slippage_bps / 100, 2, '.', ''))" :placeholder="'Standaard: '.number_format(config('trading.slippage_bps') / 100, 2)" />
                        </div>
                        <div>
                            <x-input-label for="risk_per_trade_percent" value="Risico per trade (% van account)" />
                            <x-text-input id="risk_per_trade_percent" name="risk_per_trade_percent" inputmode="decimal" class="mt-1 block w-full" :value="old('risk_per_trade_percent', number_format($user->risk_per_trade_bps / 100, 2, '.', ''))" required />
                        </div>
                        <div>
                            <x-input-label for="max_position_percent" value="Max. positiegrootte (% van account)" />
                            <x-text-input id="max_position_percent" name="max_position_percent" type="number" min="1" max="1000" class="mt-1 block w-full" :value="old('max_position_percent', $user->max_position_percent)" required />
                        </div>
                        <div>
                            <x-input-label for="min_risk_reward" value="Minimale risk/reward (bv. 1.5 = 1:1,5)" />
                            <x-text-input id="min_risk_reward" name="min_risk_reward" inputmode="decimal" class="mt-1 block w-full" :value="old('min_risk_reward', number_format($user->min_risk_reward_hundredths / 100, 2, '.', ''))" required />
                        </div>
                        <div>
                            <x-input-label for="daily_loss_limit_percent" value="Dagelijkse verlieslimiet (%, leeg = uit)" />
                            <x-text-input id="daily_loss_limit_percent" name="daily_loss_limit_percent" inputmode="decimal" class="mt-1 block w-full" :value="old('daily_loss_limit_percent', $user->daily_loss_limit_bps === null ? '' : number_format($user->daily_loss_limit_bps / 100, 2, '.', ''))" />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-300">
                        <input type="hidden" name="warn_without_stop_loss" value="0">
                        <input type="checkbox" name="warn_without_stop_loss" value="1" @checked(old('warn_without_stop_loss', $user->warn_without_stop_loss)) class="rounded border-slate-700 bg-slate-800 text-emerald-600"> Waarschuw bij orders zonder stop-loss
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-300">
                        <input type="hidden" name="leaderboard_visible" value="0">
                        <input type="checkbox" name="leaderboard_visible" value="1" @checked(old('leaderboard_visible', $user->leaderboard_visible)) class="rounded border-slate-700 bg-slate-800 text-emerald-600"> Toon mij op het leaderboard
                    </label>

                    <x-primary-button>Opslaan</x-primary-button>
                </form>
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
