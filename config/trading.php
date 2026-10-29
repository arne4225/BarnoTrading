<?php

return [
    /*
     * Marktdata-driver: "fake" of "alpaca". De Alpaca-sleutels worden uitsluitend
     * gebruikt voor het ophalen van marktdata, nooit voor orders.
     */
    'market_data_driver' => env('MARKET_DATA_DRIVER', 'fake'),

    'alpaca' => [
        'key_id' => env('ALPACA_KEY_ID'),
        'secret_key' => env('ALPACA_SECRET_KEY'),
        'data_url' => 'https://data.alpaca.markets',
        'feed' => env('ALPACA_DATA_FEED', 'iex'),
        'quote_ttl_seconds' => 5,
        'candle_ttl_seconds' => 60,
        'clock_ttl_seconds' => 30,
    ],

    'timezone' => 'America/New_York',

    'starting_cash_cents' => (int) env('TRADING_STARTING_CASH', 100000) * 100,

    'commission_cents' => (int) env('TRADING_COMMISSION_CENTS', 0),

    'slippage_bps' => (int) env('TRADING_SLIPPAGE_BPS', 0),

    'short_margin_percent' => (int) env('TRADING_SHORT_MARGIN_PERCENT', 150),

    'engine_interval_seconds' => (int) env('TRADING_ENGINE_INTERVAL_SECONDS', 5),
];
