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

    /*
     * Handmatige override om buiten beurstijd te oefenen (alleen verstandig met
     * de fake-driver). Orders worden dan altijd direct uitgevoerd.
     */
    'ignore_market_hours' => (bool) env('TRADING_IGNORE_MARKET_HOURS', false),

    /*
     * NYSE-feestdagen (volledig gesloten). Jaarlijks bijwerken.
     */
    'holidays' => [
        '2026-01-01', '2026-01-19', '2026-02-16', '2026-04-03', '2026-05-25',
        '2026-06-19', '2026-07-03', '2026-09-07', '2026-11-26', '2026-12-25',
        '2027-01-01', '2027-01-18', '2027-02-15', '2027-03-26', '2027-05-31',
        '2027-06-18', '2027-07-05', '2027-09-06', '2027-11-25', '2027-12-24',
    ],

    'starting_cash_cents' => (int) env('TRADING_STARTING_CASH', 100000) * 100,

    'commission_cents' => (int) env('TRADING_COMMISSION_CENTS', 0),

    'slippage_bps' => (int) env('TRADING_SLIPPAGE_BPS', 0),

    'short_margin_percent' => (int) env('TRADING_SHORT_MARGIN_PERCENT', 150),

    'engine_interval_seconds' => (int) env('TRADING_ENGINE_INTERVAL_SECONDS', 5),

    'fake_base_prices' => [
        'AAPL' => 19000, 'MSFT' => 42000, 'GOOGL' => 17000, 'AMZN' => 18500, 'TSLA' => 25000,
        'NVDA' => 12000, 'META' => 52000, 'SPY' => 53000, 'QQQ' => 45000, 'AMD' => 16000,
    ],

    /*
     * Zoeklijst (Alpaca Market Data biedt geen zoekfunctie). Andere tickers
     * kunnen altijd direct via hun symbool geopend worden.
     */
    'symbols' => [
        'AAPL' => 'Apple Inc.', 'MSFT' => 'Microsoft Corporation', 'GOOGL' => 'Alphabet Inc.',
        'AMZN' => 'Amazon.com Inc.', 'TSLA' => 'Tesla Inc.', 'NVDA' => 'NVIDIA Corporation',
        'META' => 'Meta Platforms Inc.', 'NFLX' => 'Netflix Inc.', 'AMD' => 'Advanced Micro Devices',
        'INTC' => 'Intel Corporation', 'SPY' => 'SPDR S&P 500 ETF', 'QQQ' => 'Invesco QQQ Trust',
        'IWM' => 'iShares Russell 2000 ETF', 'DIS' => 'Walt Disney Company', 'BA' => 'Boeing Company',
        'JPM' => 'JPMorgan Chase & Co.', 'V' => 'Visa Inc.', 'KO' => 'Coca-Cola Company',
        'PFE' => 'Pfizer Inc.', 'XOM' => 'Exxon Mobil Corporation', 'WMT' => 'Walmart Inc.',
        'COIN' => 'Coinbase Global', 'PLTR' => 'Palantir Technologies', 'UBER' => 'Uber Technologies',
        'SHOP' => 'Shopify Inc.', 'BABA' => 'Alibaba Group', 'F' => 'Ford Motor Company',
        'GME' => 'GameStop Corp.', 'AMC' => 'AMC Entertainment', 'SOFI' => 'SoFi Technologies',
    ],
];
