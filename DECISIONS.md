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
