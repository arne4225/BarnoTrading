# Nog open na fase 2

Alles uit de fase 2-opdracht is gebouwd. Dit staat nog open of is bewust beperkt:

## Niet in een echte browser geverifieerd
- De Alpine/JS-onderdelen zijn niet in een browser doorgeklikt (alleen backend, JSON en HTML-uitvoer zijn getest): candlestick-grafiek, indicator-toggles, het slepen van SL/TP-lijnen, het invullen van het orderformulier en de equity-grafiek. Doe hier een handmatige rondgang over, vooral het slepen.
- Geen browser-/end-to-end tests (bv. Pest browser tests of Dusk).

## Functioneel
- Equity curve heeft 1 punt per dag; intraday-snapshots zouden drawdown en leaderboard nauwkeuriger maken.
- Leaderboard rekent equity live uit per zichtbare gebruiker; bij veel gebruikers is een geplande berekening in `trading:snapshot` beter.
- Dagelijkse verlieslimiet blokkeert alleen nieuwe orders; al pending orders blijven staan en kunnen nog vullen.
- Waarschuwing bij positiegrootte kijkt naar order + bestaande positie op de ingevulde instapprijs, niet naar andere posities (geen portfoliobrede blootstelling).
- Journal koppelt niet aan bracket-orders of screenshots; tags zijn vrije tekst.
- Sparklines zijn dagbars (30 dagen), geen intraday. Alerts kennen alleen "koers raakt niveau" (geen % of indicator).
- Zoeken op volledige assetlijst i.p.v. de korte lijst in `config/trading.php`.

## Uit fase 1 nog niet gedaan
- Time-in-force (day/IOC), gedeeltelijke fills, bid/ask-simulatie, pre-/after-market.
- Margin calls, shortrente, dividenden, splits, fractionele aandelen, cash-reservering voor pending orders.
- WebSocket/SSE in plaats van pollen; engine als queue worker/supervisor met locking bij meerdere processen.
- Vervroegde sluitingen (13:00) en automatisch bijgewerkte feestdagen.
- Per-symbool/portfolio-brede risico-limieten.
