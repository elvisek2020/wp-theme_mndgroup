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

**Instalace**: stáhněte `mndgroup.zip` z posledního
[vydání](https://github.com/elvisek2020/wp-theme_mndgroup/releases/latest) a nahrajte ho
ve Vzhled → Šablony → Přidat → Nahrát šablonu. Před aktivací lze použít *Živý náhled*.

**Aktualizace**: šablona se aktualizuje z tohoto repozitáře (hlavička `Update URI`
ve `style.css`, kód v `inc/updater.php`). WordPress se 2× denně podívá na soubor `update.json`
v posledním vydání; novou verzi nabídne v Nástěnka → Aktualizace a – protože se po aktivaci
šablony zapnou automatické aktualizace – ji sám nainstaluje. Automatické aktualizace jdou
vypnout ve Vzhled → Šablony → MND Group → „Zakázat automatické aktualizace“.

**Vydání nové verze**:

1. Zvyšte `Version` ve `style.css` (např. `2.0.1`) a doplňte `CHANGELOG.md`.
2. Commit a push do `main` (CI zkontroluje PHP 7.4 i 8.4).
3. Vytvořte a pushněte tag se stejnou verzí:
   ```bash
   git tag v2.0.1 && git push origin v2.0.1
   ```
4. GitHub Actions šablonu zabalí a vytvoří vydání s `mndgroup.zip` a `update.json`.
   Weby si ho stáhnou do 12 hodin (nebo hned po „Zkontrolovat znovu“ v Nástěnka → Aktualizace).

Na weby se dostane jen otagovaná verze – samotný push do `main` nic nenasazuje.

## Struktura

```
mndgroup/
├── style.css               hlavička šablony
├── functions.php           načítá soubory z inc/
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
│   └── updater.php         aktualizace z GitHubu
├── .github/workflows/      CI (kontrola PHP) a vydání nové verze
├── assets/css|js|fonts|img
└── languages/              cs_CZ (.po, .mo, .l10n.php)
```

## Požadavky

WordPress 6.5+, PHP 7.4+. Polylang je volitelný (bez něj se jen nezobrazí přepínač jazyků).
Bez build kroku – CSS a JS se upravují přímo.

### Úprava překladů

Zdrojové texty jsou anglicky (kvůli EN verzi webu). Český překlad je v `languages/cs_CZ.po`;
po úpravě je potřeba vygenerovat `cs_CZ.mo` a `cs_CZ.l10n.php` (např. Poedit nebo
`wp i18n make-mo languages && wp i18n make-php languages`).
