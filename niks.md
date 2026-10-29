Je bouwt een paper trading webapplicatie in dit lege Laravel-project. Gebruikers handelen met virtueel geld in echte US-aandelen tegen echte marktprijzen. Het doel is een serieuze day trading oefenomgeving. De app draait alleen lokaal. Dit is fase 1 van 2: het fundament. Extra analysetools komen in fase 2, maar houd de architectuur daar al geschikt voor.

## Werkwijze
1. Controleer eerst de Laravel-versie (`php artisan --version`), PHP-versie en bestaande config. Pas je aanpak daarop aan.
2. Maak een kort plan met stappen en laat dat zien voordat je bouwt.
3. Bouw stap voor stap. Draai na elke stap de tests, vat kort samen wat er staat en maak een git commit.
4. Vraag alleen iets als het echt niet uit deze prompt af te leiden is. Neem anders zelf een beslissing en noteer die met reden in DECISIONS.md.

## Architectuur
- Strikt MVC volgens Laravel-conventies: Eloquent Models, dunne Controllers, Blade views.
- Businesslogica in Service-klassen (OrderService, PortfolioService, MarketDataService), niet in controllers of models.
- Form Requests voor validatie, Policies voor autorisatie, Enums voor order types, zijde en statussen.
- Frontend: Blade + Tailwind + Alpine.js. Geen SPA-framework. Donker, responsive thema.
- Database: gebruik wat het simpelst lokaal werkt (SQLite is prima). Geldbedragen nooit als float: gebruik decimal of integers in centen.
- Auth: Laravel Breeze (Blade-variant), meerdere gebruikers met elk een eigen portfolio.

## Marktdata
- Maak een interface `MarketDataProvider` met minimaal: actuele koers (quote), historische candles per timeframe, markt open/gesloten.
- Gebruik Alpaca Market Data als provider (https://data.alpaca.markets). Ik heb een Alpaca paper trading account, dus de key en secret staan in .env als ALPACA_KEY_ID en ALPACA_SECRET_KEY. Gebruik de key ALLEEN voor het ophalen van marktdata (quotes, bars, kalender/clock). Plaats of beheer nooit orders via de Alpaca Trading API: alle orders en portfolio's worden volledig in onze eigen database gesimuleerd.
- Zoek eerst de actuele gratis limieten op (feed IEX vs SIP, vertraging, calls per minuut, historische data per timeframe) en pas caching en polling daarop aan. Documenteer de beperkingen in de README.
- Zorg dat .env in .gitignore staat en zet alleen een .env.example met lege waarden in de repo.
- Maak daarnaast een `FakeMarketDataProvider` met gesimuleerde koersen (random walk). Daarmee moet de hele app ook zonder API-key werken en kunnen tests draaien. Via .env te wisselen.
- Cache responses en vang rate limits en API-fouten netjes af (duidelijke melding in de UI, geen crash).
- Geef in de UI altijd aan als koersen vertraagd zijn en met hoeveel.

## Functionaliteit fase 1
**Accounts**: registreren, inloggen, startkapitaal instelbaar (standaard $100.000), account resetten.

**Handelen**
- Orders: market, limit, stop, stop-limit. Buy en sell, plus short selling.
- Stop-loss en take-profit direct bij het plaatsen van een order (bracket/OCO: triggert de ene, dan vervalt de andere).
- Trailing stop.
- Order engine als Artisan-command (draait via scheduler of als lange loop, kies zelf en documenteer) die open orders elke paar seconden controleert tegen de actuele koers en uitvoert. Instelbare commissie en optionele slippage.
- Beurstijden (US, Eastern Time) respecteren: duidelijke status "markt open/gesloten". Orders buiten beurstijd blijven pending tot de opening.
- Validatie: genoeg koopkracht, geen verkoop van aandelen die je niet hebt (behalve bewust short), margin-regels voor shorts eenvoudig maar consistent.

**Pagina's**
- Dashboard: portfoliowaarde, cash, open posities met onrealised P&L, dagelijkse P&L, open orders.
- Aandeel zoeken en een aandeelpagina met koers, simpele grafiek en orderformulier.
- Orderhistorie en transactiehistorie.
- Watchlist met koersen.

## Kwaliteit
- Feature- en unit tests voor het kritieke deel: orderuitvoering, P&L, cash, gemiddelde instapprijs, short posities, bracket orders, trailing stop. Gebruik de FakeMarketDataProvider.
- Seeders met demo-gebruiker en demo-data.
- README met installatie, .env-uitleg en hoe je de order engine lokaal start.
- Maak een bestand DECISIONS.md met je keuzes en een TODO_FASE2.md met wat nog moet.

## Buiten scope
Geen echt geld, geen broker-koppeling voor echte orders, geen deployment, geen niet-US markten.


# fase 2
Dit is fase 2 van de paper trading app. Lees eerst README.md, DECISIONS.md, TODO_FASE2.md en verken de bestaande code, en houd je aan de bestaande architectuur (strikt MVC, logica in Services, Blade + Tailwind + Alpine.js, decimal/centen voor geld). Maak een kort plan, laat het zien en bouw dan stap voor stap met tests en een git commit per stap.

## Grafieken en indicatoren
- Candlestick grafieken met TradingView Lightweight Charts en timeframes 1m, 5m, 15m, 1u, 1d (voor zover de marktdata-provider dat toelaat; documenteer beperkingen).
- Indicatoren: SMA, EMA, RSI, MACD, volume, VWAP, aan en uit te zetten en met instelbare periodes. Bereken ze in een IndicatorService (PHP, met unit tests op bekende waarden), niet puur in JavaScript.
- Bestellen vanuit de grafiek: stop-loss en take-profit niveaus zichtbaar als lijnen op de chart, bij voorkeur te slepen.

## Risicotools
- Position size calculator: gebruiker vult risico-% van zijn account en stop-loss niveau in, de app berekent aantal aandelen, bedrag, risk/reward ratio en potentiële winst en verlies. Integreer dit in het orderformulier zodat het aantal automatisch kan worden ingevuld.
- Waarschuwingen bij slecht risicomanagement: order zonder stop-loss, positie groter dan X% van het account, risk/reward onder 1:1. Instelbaar per gebruiker.
- Optioneel dagelijks verlieslimiet: na X% dagverlies blokkeert de app nieuwe orders voor de rest van de dag.

## Analyse en journal
- Trade journal: per gesloten trade een notitie, reden van instap, tags (bijv. "breakout", "FOMO") en een zelfbeoordeling.
- Statistieken: win rate, gemiddelde winst en verlies, profit factor, expectancy, max drawdown, beste en slechtste trade, resultaat per tag en per weekdag/uur. Equity curve en drawdown grafiek.
- Export van trades als CSV.

## Overig
- Watchlist verbeteren: meerdere lijsten, sorteren, mini-grafiekjes (sparklines), prijsalerts (melding in de UI wanneer een koers een niveau raakt).
- Leaderboard tussen gebruikers op rendement (periode kiezen: dag, week, maand, totaal). Alleen publiek zichtbaar voor gebruikers die dat aanzetten.
- Instellingenpagina: commissie, slippage, risicolimieten, startkapitaal.
- Performance: let op N+1 queries, cache zware berekeningen.

## Kwaliteit
Tests voor indicatoren, position sizing, statistieken en het leaderboard. Werk README en DECISIONS.md bij en laat aan het einde zien wat er nog open staat.