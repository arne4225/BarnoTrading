# BarnoTrading — paper trading voor US-aandelen

Webapplicatie om te oefenen met day trading met virtueel geld tegen echte (of gesimuleerde) marktprijzen. Alle orders, posities en portfolio's worden volledig in de eigen database gesimuleerd. Er worden **nooit** orders naar een broker gestuurd.

Stack: Laravel 13, PHP 8.5, SQLite, Blade + Tailwind + Alpine.js, Laravel Breeze, Pest.

## Installatie

```bash
composer install
pnpm install            # of npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed   # demo-gebruiker: demo@example.com / password
pnpm run build               # of: pnpm run dev
php artisan serve
```

Open http://localhost:8000. Zonder API-key draait de app direct op gesimuleerde koersen.

## .env

| Variabele | Betekenis |
|---|---|
| `MARKET_DATA_DRIVER` | `fake` (gesimuleerde random walk, geen key nodig) of `alpaca` |
| `ALPACA_KEY_ID`, `ALPACA_SECRET_KEY` | Alpaca-sleutels. Worden **alleen** gebruikt voor marktdata (`data.alpaca.markets`) |
| `ALPACA_DATA_FEED` | `iex` (gratis, standaard) of `sip` (betaald) |
| `TRADING_STARTING_CASH` | Startkapitaal voor nieuwe accounts in hele dollars (standaard 100000) |
| `TRADING_COMMISSION_CENTS` | Commissie per uitgevoerde order, in centen (standaard 0) |
| `TRADING_SLIPPAGE_BPS` | Slippage in basispunten (100 = 1%) op market-, stop- en trailing-fills (standaard 0) |
| `TRADING_SHORT_MARGIN_PERCENT` | Onderpand voor shorts als % van de shortwaarde (standaard 150) |
| `TRADING_ENGINE_INTERVAL_SECONDS` | Controle-interval van de order engine (standaard 5) |
| `TRADING_IGNORE_MARKET_HOURS` | `true` om buiten beurstijd te oefenen (handig met de fake-driver) |

`.env` staat in `.gitignore`; alleen `.env.example` (met lege sleutels) zit in de repo.

## De order engine starten

Open orders (limit, stop, stop-limit, trailing stop, bracket-poten) worden niet door de webserver uitgevoerd maar door een Artisan-command. Start het in een eigen terminal:

```bash
php artisan trading:engine            # lange loop, controleert elke 5 seconden
php artisan trading:engine --interval=2
php artisan trading:engine --once     # één ronde (voor cron/tests)
```

Market-orders worden bij het plaatsen direct uitgevoerd als de markt open is. Buiten beurstijd (09:30–16:00 Eastern Time, werkdagen, zonder NYSE-feestdagen) blijft alles `pending` en pakt de engine het op zodra de markt opent.

Voor de dagelijkse P&L-basis (slotstand per dag) draait daarnaast `trading:snapshot` om 16:05 ET via de scheduler: start `php artisan schedule:work` of zet `* * * * * php artisan schedule:run` in cron. Zonder scheduler wordt de stand ook bijgewerkt telkens je het dashboard opent.

## Alpaca-limieten (gratis plan) en gevolgen

Gebaseerd op de Alpaca-documentatie op het moment van bouwen; controleer actuele cijfers op https://docs.alpaca.markets/docs/about-market-data-api.

- **Feed**: gratis = IEX (alleen beurs IEX, dus kleiner volume dan de consolidated tape). SIP (volledige markt) is betaald.
- **Vertraging**: de laatste 15 minuten SIP-data zijn niet opvraagbaar met het gratis plan; IEX-data is vrijwel realtime. De UI toont bij elke koers de leeftijd ervan ("realtime", "30 sec vertraagd", ...).
- **Rate limit**: ca. 200 calls per minuut. Daarom: koersen worden 5 seconden gecachet per symbool, meerdere symbolen gaan in één snapshot-call, candles worden 60 seconden gecachet en bij HTTP 429 toont de app de laatst bekende koers met een duidelijke melding.
- **Historische candles**: 1min, 5min, 15min, 1uur en 1dag via de bars-endpoint met IEX-feed.
- De marktklok en feestdagen komen uit een lokale kalender (zie `config/trading.php`, jaarlijks bijwerken) in plaats van de Alpaca Trading API.

## Fase 2: functies

- **Grafiek** (aandeelpagina): candlesticks met TradingView Lightweight Charts, timeframes 1m/5m/15m/1u/1d, ververst elke 15 s. Indicatoren (SMA, EMA, VWAP, volume, RSI, MACD) aan/uit met eigen periodes, bv. SMA `20,50`. Ze worden door `IndicatorService` in PHP berekend en als JSON geleverd (`/stocks/{symbol}/chart`).
- **Stop-loss / take-profit op de grafiek**: knoppen "Stop-loss lijn" / "Take-profit lijn" zetten sleepbare lijnen; de niveaus worden live in het orderformulier ingevuld.
- **Risicotools**: position size calculator ("Vul aantal in" op basis van risico-% en stop-loss), waarschuwingen (geen stop-loss, positie te groot, risk/reward te laag) en een optionele dagelijkse verlieslimiet. Alles is per gebruiker instelbaar onder **Instellingen**.
- **Journal** (`/journal`): reden van instap, notitie, tags en zelfbeoordeling per trade (een trade = positie openen tot ze weer op 0 staat). **CSV-export** via `/journal/export`.
- **Statistieken** (`/statistics`): win rate, gemiddelde winst/verlies, profit factor, expectancy, max drawdown, beste/slechtste trade, resultaat per tag, weekdag en uur, equity curve en drawdown.
- **Watchlists**: meerdere lijsten, sorteren op symbool/koers/dag-%, sparklines (30 dagen) en prijsalerts. Alerts worden bij elke paginaweergave en door `trading:engine` gecontroleerd; een melding verschijnt bovenaan de pagina.
- **Leaderboard** (`/leaderboard`): rendement per dag/week/maand/totaal. Alleen gebruikers die dat aanzetten staan erop, en alleen naam en percentage worden getoond.

### Beperkingen van de grafiek per provider

- **Alpaca gratis (IEX)**: candles komen alleen uit IEX-handel. Bij weinig verhandelde aandelen ontbreken daardoor minutenbars of is het volume veel lager dan de echte markt. Alle vijf timeframes worden ondersteund; er worden maximaal 300 candles per aanvraag opgehaald (voor 1m is dat dus iets minder dan één handelsdag).
- Elke grafiekverversing is één bars-call (candles worden 60 s gecachet) en past binnen de limiet van ~200 calls/min.
- **Fake provider**: candles zijn synthetisch (deterministisch per symbool), bedoeld om de app te testen.
- Tijden op de grafiek zijn Eastern Time. Daguitwisseling van VWAP gebeurt op ET-dagen.
- Sparklines en dag-% in de watchlist gebruiken dagbars (30 stuks, 5 min gecachet): één extra call per symbool per 5 minuten.

## Architectuur in het kort

- `app/Contracts/MarketDataProvider` — interface; implementaties: `FakeMarketDataProvider`, `AlpacaMarketDataProvider`. `MarketDataService` voegt foutafhandeling en "laatst bekende koers" toe.
- `app/Services/OrderService` — plaatsen, annuleren, uitvoeren, bracket/OCO, trailing stops.
- `app/Services/PortfolioService` — cash, posities, P&L, margin, reset.
- `IndicatorService`, `ChartDataService`, `PositionSizingService`, `RiskService`, `StatisticsService`, `TradeExportService`, `WatchlistService`, `AlertService`, `LeaderboardService` — fase 2, allemaal in `app/Services`.
- Controllers zijn dun; validatie in Form Requests, autorisatie in Policies, enums voor type/zijde/status.
- Alle geldbedragen zijn gehele centen.

## Tests

```bash
php artisan test --compact
```

Frontend: `pnpm run build` (of `pnpm run dev`) bouwt Tailwind, Alpine en Lightweight Charts.

Tests draaien tegen de `FakeMarketDataProvider` met vaste koersen; er gaan geen echte API-calls uit.
