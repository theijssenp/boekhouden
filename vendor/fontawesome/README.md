# Font Awesome (lokaal gehost)

**Versie:** 6.4.0 Free
**Herkomst:** https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/

Voorheen werd Font Awesome van de CDN van Cloudflare geladen. Dat betekende
een externe request bij elke paginalading en onzichtbare iconen zodra er geen
internetverbinding is. De bestanden staan nu lokaal.

## Wat er is meegenomen

| Bestand | Waarom |
|---|---|
| `css/fontawesome.min.css` | kern + de naam/codepoint-regels van **alle** iconen |
| `css/solid.min.css` | `@font-face` voor de solid-variant |
| `webfonts/fa-solid-900.woff2` | de solid glyphs |

De app gebruikt uitsluitend de **solid**-variant (`fas`), dus `regular` en
`brands` zijn niet meegenomen. Dat scheelt ruim 1 MB.

De `.ttf`-fallback (394 kB) is bewust weggelaten: `woff2` wordt ondersteund
door elke browser sinds ongeveer 2016.

Er is met opzet **niet** gesubset op de 66 iconen die nu in gebruik zijn.
Een subset zou nog eens ~140 kB schelen, maar dan levert elk nieuw icoon een
leeg vakje op totdat iemand de subset opnieuw genereert. Dat risico weegt
niet op tegen de winst.

## Bijwerken

1. Haal `fontawesome.min.css`, `solid.min.css` en `fa-solid-900.woff2` op bij
   de gewenste versie op cdnjs (zie URL hierboven).
2. Verwijder in `solid.min.css` de `url(../webfonts/fa-solid-900.ttf)
   format("truetype")` uit de `src:` van de `@font-face`.
3. Controleer daarna of alle iconen nog renderen — een versiesprong kan
   iconen hernoemen (`fa-exchange-alt` heet in v6 bijvoorbeeld
   `fa-right-left`; de oude naam werkt nog als alias).

De verwijzingen staan op één plek: `php/page_header.php` (en `login.php`,
die de gedeelde header niet gebruikt).

## Licentie

Iconen CC BY 4.0, fonts SIL OFL 1.1, code MIT — zie
https://fontawesome.com/license/free
