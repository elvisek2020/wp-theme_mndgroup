# Šablona MND Group

Vlastní šablona pro www.mndgroup.eu. Hybrid: PHP šablony + `theme.json`. Bez jQuery, bez build kroku, bez externích služeb (písmo je lokálně). Nástupce šablony „DP Enigmatic for MND Group“ (2018) se stejným vzhledem.

## Struktura
| Cesta | Obsah |
|---|---|
| `functions.php` | seznam modulů v `inc/` (každý jde vypnout) |
| `inc/setup.php` | podpora šablony, menu `primary`, widgety `sidebar-footer`, úklid widgetů po aktivaci |
| `inc/customizer.php` | Vzhled → Přizpůsobit → MND Group (bannery, interval, logo KKCG, GA4 ID) |
| `inc/assets.php` | CSS/JS, přednačtení písem, Google Analytics (jen s vyplněným ID) |
| `inc/cleanup.php` | emoji, zbytečné odkazy v `<head>` |
| `inc/template-tags.php` | logo, přepínač jazyků, copyright |
| `inc/post-types.php` | typ obsahu `slide` (bannery) – stejný název jako v původní šabloně |
| `inc/navigation.php` | dlaždice: ikony podle pořadí (nebo CSS třídy `icon-oil` …) a rozbalovací tlačítka |
| `inc/polylang.php` | překládané slidy, převzetí přiřazení menu po přepnutí šablony |
| `inc/updater.php` | aktualizace šablony z GitHub Releases |
| `inc/plugin-check.php` | upozornění v administraci, když chybí plugin MND Group Core |
| `page-homepage.php` | úvodní stránka (CZ i EN): bannery, dlaždice, text |
| `template-parts/hero.php` | bannery – prezentace nebo pod sebou |
| `template-parts/segments.php` | dlaždice oblastí podnikání se seznamem společností |
| `theme.json` | paleta (odkazuje na `--mnd-*`), písmo Montserrat (`fontFace`), šířka obsahu |
| `assets/css/main.css` | barvy a rozměry jako `--mnd-*` proměnné nahoře v souboru, pak styly |
| `assets/css/editor.css`, `print.css` | editor, tisk |
| `assets/js/theme.js` | prezentace bannerů (pauza, tečky, swipe, klávesnice), rozbalování dlaždic |
| `assets/fonts/` | Montserrat 400–700, latin + latin-ext (OFL) |
| `assets/img/` | logo, ikony dlaždic (SVG), mapa, překryv banneru |
| `languages/` | čeština (`cs_CZ.po`, `.mo`, `.l10n.php`); zdrojové texty anglicky kvůli EN verzi webu |

Prefix všeho: `mnd_` (PHP), `MND_` (konstanty), `mnd-` (CSS třídy, proměnné, handly skriptů).

## Obsah
- **Bannery**: Slidy → název (= text v banneru) + náhledový obrázek 1920 × 484 px, pořadí polem *Pořadí*; každý slide má jazykovou verzi v Polylangu.
- **Dlaždice a společnosti**: Vzhled → Menu → „Oblasti podnikání (dlaždice)“ pro každý jazyk. Hlavní položky = dlaždice, podpoložky = společnosti, položka s odkazem `#` jen rozbaluje seznam.
- **Patička**: Vzhled → Widgety → „Patička – kontakt“, s Polylangem jeden widget pro každý jazyk.

## Související
- `../../plugins/mndgroup-core/` — plugin MND Group Core: funkce nezávislé na šabloně (přihlášení, bezpečnost, administrace, údržba webu)
