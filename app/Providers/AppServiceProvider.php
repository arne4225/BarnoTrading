<?php

namespace App\Providers;

use App\Contracts\MarketDataProvider;
use App\Services\MarketData\AlpacaMarketDataProvider;
use App\Services\MarketData\FakeMarketDataProvider;
use App\Services\MarketData\MarketCalendar;
use App\Services\MarketData\MarketDataService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MarketCalendar::class);

        $this->app->singleton(MarketDataProvider::class, fn ($app) => match (config('trading.market_data_driver')) {
            'alpaca' => $app->make(AlpacaMarketDataProvider::class),
            default => $app->make(FakeMarketDataProvider::class),
        });

        $this->app->singleton(FakeMarketDataProvider::class);
        $this->app->scoped(MarketDataService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
