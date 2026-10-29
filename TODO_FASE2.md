# TODO fase 2

## Analyse en grafieken
- Candlestick-grafiek met volume, zoom en indicatoren (SMA/EMA, RSI, MACD, VWAP). De `MarketDataProvider::candles()` en `Candle`-DTO zijn hier al op voorbereid.
- Equity curve en drawdown op basis van `portfolio_snapshots` (nu 1 per dag; intraday-snapshots toevoegen).
- Performance-statistieken: winrate, gemiddelde winst/verlies, expectancy, Sharpe, per symbool en per dag/uur.
- Trade-journal: notities en tags per order/transactie, screenshots.

## Handelen
- Time-in-force (day/GTC/IOC), gedeeltelijke fills en orderboek-simulatie met bid/ask in plaats van last price.
- Pre-/after-market sessies, vervroegde sluitingen en automatisch bijgewerkte feestdagenkalender (nu handmatig in config).
- Margin-calls en geforceerd liquideren, short-rente en leenkosten, dividenden en splits.
- Fractionele aandelen en reservering van cash voor pending orders.
- OCO/OTO los van bracket, order wijzigen in plaats van annuleren en opnieuw plaatsen.

## Marktdata en realtime
- WebSocket-stream (Alpaca) of server-sent events in plaats van polling; engine event-driven maken.
- Zoekfunctie op volledige assetlijst (eenmalig importeren) i.p.v. de korte lijst in config.
- Alerts op koersniveaus.

## Techniek
- Engine als queue worker/Horizon-job of supervisor-service; per-symbool locking bij meerdere engine-processen.
- Browser-/end-to-end tests voor het orderformulier (Alpine).
- Per-gebruiker commissie- en slippage-instellingen in de database.
- Exports (CSV) van orders en transacties.
