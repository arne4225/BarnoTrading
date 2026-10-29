<?php

namespace Database\Seeders;

use App\Contracts\MarketDataProvider;
use App\Data\PlaceOrderData;
use App\Enums\OrderSide;
use App\Enums\OrderType;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PortfolioService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo-gebruiker (demo@example.com / password) met posities, orders en watchlist.
 * Draait altijd tegen de actuele provider; de beursuren worden tijdens het
 * seeden genegeerd zodat de demo-orders ook buiten openingstijd uitgevoerd worden.
 */
class DemoSeeder extends Seeder
{
    public function run(OrderService $orders, PortfolioService $portfolio): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo@example.com'],
            ['name' => 'Demo Trader', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );

        $portfolio->resetAccount($user, config('trading.starting_cash_cents'));
        $user->watchlists()->delete();
        $main = $user->watchlists()->create(['name' => 'Standaard']);
        foreach (['AAPL', 'MSFT', 'NVDA', 'TSLA', 'SPY'] as $symbol) {
            $main->items()->create(['user_id' => $user->id, 'symbol' => $symbol]);
        }
        $growth = $user->watchlists()->create(['name' => 'Groei']);
        foreach (['AMD', 'PLTR', 'SHOP'] as $symbol) {
            $growth->items()->create(['user_id' => $user->id, 'symbol' => $symbol]);
        }

        $originalHours = config('trading.ignore_market_hours');
        config(['trading.ignore_market_hours' => true]);

        try {
            $place = fn (string $symbol, OrderSide $side, OrderType $type, int $qty, array $extra = []) => $orders->place(
                $user,
                new PlaceOrderData(...['symbol' => $symbol, 'side' => $side, 'type' => $type, 'quantity' => $qty, ...$extra]),
            );

            $place('AAPL', OrderSide::Buy, OrderType::Market, 50);
            $place('AAPL', OrderSide::Sell, OrderType::Market, 20);
            $place('MSFT', OrderSide::Buy, OrderType::Market, 15);
            $place('TSLA', OrderSide::Sell, OrderType::Market, 10, ['isShort' => true]);

            $nvda = app(MarketDataProvider::class)->quote('NVDA')->priceCents;
            $place('NVDA', OrderSide::Buy, OrderType::Limit, 25, [
                'limitPriceCents' => (int) round($nvda * 0.95),
                'takeProfitCents' => (int) round($nvda * 1.05),
                'stopLossCents' => (int) round($nvda * 0.90),
            ]);

            $aapl = app(MarketDataProvider::class)->quote('AAPL')->priceCents;
            $place('AAPL', OrderSide::Sell, OrderType::TrailingStop, 20, ['trailPercentBps' => 300]);
            $place('AAPL', OrderSide::Sell, OrderType::Limit, 5, ['limitPriceCents' => (int) round($aapl * 1.10)]);
        } finally {
            config(['trading.ignore_market_hours' => $originalHours]);
        }

        $portfolio->summary($user);
    }
}
