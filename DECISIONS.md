# Beslissingen

## Geld en prijzen
- Alle bedragen en koersen zijn gehele **centen** (`bigint`); aantallen zijn gehele aandelen (geen fracties). Floats komen alleen aan de API-rand (Alpaca JSON → cents) voor.
- Gemiddelde instapprijs is afgeleid: we bewaren de totale **kostenbasis** per positie (incl. commissie bij long) in plaats van een afgeronde gemiddelde prijs. Zo blijft de boekhouding exact en geldt altijd: gerealiseerd + onrealised = equity − startkapitaal.

## Posities, shorts en margin
- `positions.quantity` is signed (negatief = short). Een order mag **niet door nul heen** gaan: eerst volledig afdekken, dan pas long gaan (voorkomt dubbelzinnige P&L).
- Verkopen zonder bezit kan alleen bewust via "Short" (`is_short`). Gewoon verkopen van meer dan je bezit wordt geweigerd.
- Eén margin-regel voor alles: vrije koopkracht = cash − 150% × marktwaarde van shorts (het volledige onderpand, incl. de shortopbrengst, is geblokkeerd). Een order wordt geweigerd als de koopkracht daarna negatief wordt én slechter is dan ervoor. Daardoor kan afdekken altijd, ook bij verlies. Percentage instelbaar.
- Koopkracht wordt bij plaatsen én bij uitvoeren getoetst; pending orders reserveren geen cash. Een pending order kan dus alsnog `Rejected` worden als het geld er bij uitvoering niet meer is.

## Orders
- Alle orders zijn effectief GTC (geldig tot geannuleerd); er is geen "day"-geldigheid.
- Geen gedeeltelijke fills: een order wordt in één keer uitgevoerd tegen de laatste koers (`last trade`).
- Limit: uitvoering tegen de actuele koers (die is gelijk aan of beter dan de limiet). Stop/trailing: worden market-orders na de trigger (gap-risico en slippage dus mogelijk, zoals in het echt). Stop-limit: eerst triggeren, daarna limit-regels.
- Slippage geldt alleen voor market-, stop- en trailing-fills, nooit voor limit-fills.
- Bracket: take-profit (limit) en stop-loss (stop) zijn kind-orders met status `Held` tot de entry vult; daarna `Pending`. Vult één poot, dan wordt de andere geannuleerd (OCO). Annuleren/afwijzen van de entry annuleert de poten. Trailing stops hebben geen bracket.
- Trailing stop: bedrag óf percentage (in basispunten), werkt voor sell (long) en buy (short afdekken). De extreme koers wordt door de engine bijgehouden vanaf plaatsing.
- Ongeldige orders bij plaatsing geven een foutmelding en worden niet opgeslagen; orders die bij uitvoering falen worden bewaard als `Rejected` met reden.

## Order engine
- Een Artisan-command met lange loop (`trading:engine`, standaard elke 5 s) in plaats van de scheduler, omdat de Laravel scheduler maximaal per minuut draait. Voor dagelijkse snapshots wordt wél de scheduler gebruikt.
- De engine slaat orders over als de koers `stale` is (API-probleem), zodat er nooit op oude koersen wordt uitgevoerd.

## Marktdata
- Marktklok en feestdagen zijn lokaal (09:30–16:00 ET, NYSE-feestdagen in config) voor beide providers: bruikbaar zonder key en met één bron van waarheid. Vervroegde sluitingen (13:00) worden niet gemodelleerd.
- Alpaca Market Data kent geen zoekfunctie; zoeken gaat over een korte lijst populaire tickers in `config/trading.php`. Elk geldig ticker-symbool kan ook direct geopend worden.
- De fake provider bewaart zijn staat in de cache zodat webproces en engine dezelfde koersen zien. Tests gebruiken `freeze()` en `setPrice()` voor determinisme.
- `TRADING_IGNORE_MARKET_HOURS` bestaat om buiten beurstijd met de fake-driver te kunnen oefenen; standaard uit.

## Overig
- Dagelijkse P&L = huidige equity − equity van de laatste eerdere dag-snapshot (anders startkapitaal). Snapshots komen van `trading:snapshot` (16:05 ET) en worden bij elk dashboardbezoek bijgewerkt.
- Account resetten wist orders, posities, transacties en snapshots, en laat de watchlist staan. Het startkapitaal is bij het resetten instelbaar.
- Breeze (Blade) installeerde Tailwind 3; we blijven daarbij. Donker thema via `darkMode: 'class'` met `class="dark"` op `<html>` en aangepaste Breeze-componenten.
- Frontendbuild via pnpm omdat `npm` niet op deze machine staat.
- Lokalisatie: UI-teksten zijn in het Nederlands, code en identifiers in het Engels.

# Fase 2

## Grafieken en indicatoren
- Indicatoren rekenen op floats in dollars (het zijn weergave-reeksen, geen boekhouding); candles komen binnen als centen. Geld blijft overal elders integer.
- EMA start met de SMA van de eerste N waarden, RSI gebruikt Wilder-smoothing; beide zijn getest tegen de StockCharts-referentiewaarden. VWAP begint elke ET-handelsdag opnieuw en gebruikt de typical price (H+L+C)/3.
- Lightweight Charts kent alleen UTC; tijden worden server-side met de Eastern Time-offset verschoven. RSI en MACD staan in eigen panes (Lightweight Charts v5), volume als overlay.
- Slepen van SL/TP-lijnen is zelf gebouwd (hit-test op de lijn, `coordinateToPrice`) omdat de library dat niet biedt. De niveaus vullen het orderformulier via een browser-event.
- Grafiekdata wordt 5 s gecachet; bij een providerfout wordt niets gecachet.

## Risico
- Position size = floor(equity × risico-bps / risico per aandeel). Afronden naar beneden zodat het risico nooit overschreden wordt; bij long ook begrensd door de koopkracht.
- Waarschuwingen blokkeren nooit; ze verschijnen live in het formulier en na het plaatsen. Orders die een positie verkleinen krijgen geen waarschuwingen.
- Dagelijkse verlieslimiet: dagverlies t.o.v. de laatste slotstand (of startkapitaal). Zodra de limiet geraakt is wordt dat vastgelegd (`trading_blocked_on`) en blijft de blokkade de rest van die ET-dag staan, ook als de koers herstelt. Alleen positie-vergrotende orders worden geblokkeerd; sluiten blijft altijd mogelijk. Orders die al pending staan worden door de limiet niet geraakt.
- Instellingen zijn opgeslagen in eenheden zonder floats: commissie in centen, slippage/risico/verlieslimiet in basispunten, min. risk/reward in honderdsten. Lege commissie/slippage betekent: standaard uit `.env`. Startkapitaal wijzig je alleen via reset (anders klopt de P&L-historie niet).

## Journal en statistieken
- Een "trade" is een round-trip (positie 0 → niet-0 → 0), bijgehouden in `PortfolioService::applyFill` in de tabel `trades`. Transacties verwijzen ernaar. Gerealiseerd resultaat is netto na commissie.
- Statistieken tellen alleen gesloten trades. Per tag telt een trade met meerdere tags in elke tag mee. Weekdag/uur gaan op het openingsmoment (ET). Resultaten worden 60 s gecachet met een sleutel die verandert zodra er een trade of snapshot bijkomt.
- Equity curve en max drawdown komen uit de dagelijkse snapshots, dus 1 punt per dag; dat is grof maar consistent met de dagelijkse P&L.
- CSV: UTF-8 met BOM voor Excel, bedragen als platte decimalen, en tekstvelden die met `=`, `+`, `-` of `@` beginnen krijgen een `'` ervoor tegen formule-injectie.

## Watchlist, alerts, leaderboard
- Watchlist-items houden `user_id` én `watchlist_id` (autorisatie blijft eenvoudig). Bestaande items zijn gemigreerd naar een lijst "Standaard".
- Alerts hebben een richting die bij aanmaken uit de huidige koers volgt; ze gaan één keer af en blijven zichtbaar tot de gebruiker ze wegklikt. Controle gebeurt bij paginaweergave én in `trading:engine`, zodat het ook zonder engine werkt.
- Leaderboard toont alleen naam, rendement in % en aantal gesloten trades (geen bedragen of e-mail), alleen voor gebruikers die zich aanmelden. Rendement = (equity nu − equity voor de periode) / equity voor de periode, 60 s gecachet. Equity nu wordt met actuele koersen berekend, dus het kost per zichtbare gebruiker een koers-call per 60 s.
- `Model::preventLazyLoading` staat aan buiten productie om N+1's direct te zien.
