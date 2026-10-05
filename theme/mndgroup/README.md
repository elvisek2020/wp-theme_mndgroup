# Šablona MND Group 2.0

Šablona pro rozcestník [www.mndgroup.eu](https://www.mndgroup.eu). Nahrazuje původní šablonu
„DP Enigmatic for MND Group“ (2018) se stejným vzhledem, ale přepsanou od základu.

## Co se změnilo proti původní šabloně

- **Bez jQuery a knihovny Slick** – vlastní skript má 7 kB. Původní skript na jQuery 3 padal
  (`.live is not a function`), takže prezentace bannerů vůbec nefungovala a bannery se
  zobrazovaly pod sebou.
- **Ikony 1 kB místo 765 kB** – původní SVG ikony obsahovaly data z Illustratoru (5 × 765 kB).
- **Písmo Montserrat hostované lokálně** – žádné požadavky na Google Fonts (GDPR).
- **Responzivní obrázky** (`srcset`), první banner se načítá přednostně, ostatní na pozadí.
- **Přístupnost** – ovládání klávesnicí, pauza prezentace, respektuje „omezit pohyb“ v systému,
  kontrast zeleného textu na dlaždicích splňuje WCAG AA (dlaždice jsou o odstín tmavší).
- **Dotyková zařízení** – seznam společností se na mobilu a tabletu rozbalí šipkou
  (původně jen najetím myší).
- Anglická verze má anglické popisky ovládacích prvků, čeština je v `languages/`.

## Kompatibilita s obsahem webu

Šablona záměrně zachovává názvy, takže **po aktivaci se nic nemusí přenastavovat**:

| Co | Jak |
|---|---|
| Úvodní stránky CZ/EN | šablona stránky `page-homepage.php` („Šablona úvodní stránky“) |
| Bannery | typ obsahu `slide` (název + náhledový obrázek, pořadí = pole „Pořadí“) |
| Dlaždice s firmami | umístění menu `primary` |
| Adresa v patičce | oblast widgetů `sidebar-footer` |
| Menu v Polylangu | po aktivaci se přiřazení menu pro CZ i EN převezme z původní šablony |
| Widgety | výchozí widgety z nepoužívané postranní lišty se přesunou mezi neaktivní |

## Správa obsahu

- **Bannery**: Slidy → název (= text v banneru) + náhledový obrázek **1920 × 484 px**.
  Pořadí určuje pole *Pořadí* (vpravo v editaci slidu). Každý slide má jazykovou verzi v Polylangu.
- **Dlaždice a firmy**: Vzhled → Menu → menu přiřazené k „Oblasti podnikání (dlaždice)“
  pro každý jazyk. Hlavní položky = dlaždice, podpoložky = firmy. Položka s odkazem `#`
  jen rozbaluje seznam. Ikony se přiřazují podle pořadí (těžba, vrtání, skladování, plamen,
  šipky); jinou ikonu lze vynutit CSS třídou položky `icon-oil`, `icon-drill`, `icon-gas`,
  `icon-fire` nebo `icon-arrows`.
- **Patička**: Vzhled → Widgety → „Patička – kontakt“. S Polylangem přidejte jeden textový
  widget pro každý jazyk a u widgetu nastavte jazyk.
- **Vzhled → Přizpůsobit → MND Group**:
  - bannery jako *prezentace* (výchozí) nebo *všechny pod sebou* (jako dnes na webu),
  - interval střídání,
  - logo KKCG v patičce,
  - ID měření Google Analytics 4 (náhrada pluginu MonsterInsights).
- Vlastní logo lze nahrát v Přizpůsobit → Základní informace (jinak se použije logo MND Group).

## Instalace a aktualizace

Šablona se instaluje a vydává společně s pluginem **mndgroup-core** (bezpečnost, administrace,
údržba) – postup je v [README repozitáře](../../README.md). Bez pluginu šablona funguje, jen
v administraci upozorní, že chybí.

Aktualizace: hlavička `Update URI` ve `style.css` a `inc/updater.php` – WordPress si novou verzi
bere z posledního vydání na GitHubu (soubor `update.json`). Na lokálním vývoji
(`WP_ENVIRONMENT_TYPE=local`) se aktualizace nehledají.

## Struktura

```
mndgroup/
├── style.css               hlavička šablony (Version = verze pluginu)
├── theme.json              paleta, písmo a šířka obsahu pro blokový editor
├── functions.php           seznam modulů z inc/
├── header.php, footer.php
├── page-homepage.php       úvodní stránka (bannery + dlaždice + text)
├── index.php, 404.php      ostatní stránky
├── template-parts/         hero.php, segments.php, content*.php
├── inc/
│   ├── setup.php           podpora funkcí, menu, widgety, úklid widgetů po aktivaci
│   ├── assets.php          CSS/JS, přednačtení písem, Google Analytics
│   ├── cleanup.php         emoji, zbytečné odkazy v hlavičce
│   ├── post-types.php      typ obsahu slide
│   ├── navigation.php      ikony a rozbalovací tlačítka dlaždic
│   ├── customizer.php      nastavení v Přizpůsobení
│   ├── template-tags.php   logo, přepínač jazyků, copyright
│   ├── polylang.php        překládané slidy, převzetí menu po aktivaci
│   ├── updater.php         aktualizace z GitHub Releases
│   └── plugin-check.php    upozornění, když chybí plugin mndgroup-core
├── assets/
│   ├── css/                tokens.css (proměnné), theme.css, print.css, fonts.css, editor.css
│   ├── js/theme.js         prezentace bannerů, rozbalování dlaždic
│   ├── fonts/              Montserrat (woff2, licence OFL)
│   └── img/                logo, ikony dlaždic, mapa, překryv banneru
└── languages/              cs_CZ (.po, .mo, .l10n.php)
```

Všechny třídy, proměnné a funkce mají prefix `mnd-` / `mndgroup_`.

## Požadavky

WordPress 6.5+, PHP 7.4+. Polylang je volitelný (bez něj se jen nezobrazí přepínač jazyků).
Bez build kroku – CSS a JS se upravují přímo.

### Úprava překladů

Zdrojové texty jsou anglicky (kvůli EN verzi webu). Český překlad je v `languages/cs_CZ.po`;
po úpravě je potřeba vygenerovat `cs_CZ.mo` a `cs_CZ.l10n.php` (např. Poedit nebo
`wp i18n make-mo languages && wp i18n make-php languages`).
