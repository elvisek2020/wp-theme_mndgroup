# MND Group — WordPress šablona a plugin

[![Release](https://img.shields.io/github/v/release/elvisek2020/wp-theme_mndgroup?label=release)](https://github.com/elvisek2020/wp-theme_mndgroup/releases/latest)
![WordPress](https://img.shields.io/badge/WordPress-6.5%2B-21759b)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)

Vlastní šablona a doprovodný plugin pro rozcestník skupiny MND [www.mndgroup.eu](https://www.mndgroup.eu) — vzhled původní šablony z roku 2018, ale rychlé, bez jQuery, bez build kroku a bez externích služeb.

![Náhled šablony](theme/mndgroup/screenshot.png)

| Balíček | Typ | Popis |
|---|---|---|
| **MND Group** (`theme/mndgroup`) | šablona | vzhled webu: bannery, dlaždice se společnostmi, text, patička s mapou, čeština a angličtina |
| **MND Group Core** (`plugins/mndgroup-core`) | plugin | funkce nezávislé na šabloně: přihlášení, bezpečnost, administrace, údržba |

Obojí se vydává společně se stejným číslem verze a aktualizuje se přímo z GitHub Releases.

---

## Šablona MND Group

**Vzhled**
- Stejný vzhled jako původní šablona: hlavička s logem, bannery s bílým „šípem“, tmavé dlaždice se zelenými popisky a ikonami, šedý text a patička s mapou
- Bannery jako prezentace (prolínání, tečky, pauza, swipe, klávesnice) nebo všechny pod sebou
- Dlaždice oblastí podnikání se seznamem společností – na počítači po najetí myší, na mobilu a tabletu rozbalovací šipkou
- Přepínač jazyků CS / EN (Polylang), odkaz vede na překlad aktuální stránky
- Responzivní od 320 px, bannery i na mobilu

**Technicky**
- Hybridní šablona: PHP šablony a `theme.json`, moduly v `inc/` jdou vypnout jednotlivě
- Žádné jQuery ani knihovny, vlastní skript 7 kB; úvodní stránka 9 požadavků / 237 kB (původní 33 / 4,8 MB)
- Písmo Montserrat lokálně (z `theme.json`), žádné Google Fonts ani CDN
- Přístupnost: kontrast WCAG AA, ovládání klávesnicí, popisky pro čtečky, respektuje „omezit pohyb“
- Kompatibilní s obsahem původní šablony (typ obsahu `slide`, menu `primary`, widgety `sidebar-footer`) – po aktivaci převezme menu v Polylangu a uklidí widgety
- Google Analytics 4 jen s vyplněným ID

**Nastavení** — *Vzhled → Přizpůsobit → MND Group*

| Volba | Popis |
|---|---|
| Bannery na úvodní stránce | prezentace / všechny pod sebou |
| Interval střídání | 3–20 s |
| Logo KKCG | v patičce na velkých obrazovkách |
| Google Analytics 4 | ID měření (prázdné = bez měření) |

Bannery se spravují v *Slidy* (název + obrázek 1920 × 484 px, pořadí polem *Pořadí*), dlaždice a společnosti ve *Vzhled → Menu*, adresa v patičce ve *Vzhled → Widgety*.

---

## Plugin MND Group Core

Nahrazuje čtyři dřívější pluginy (All-In-One Security, Simple Login Log, Admin Menu Editor, Server IP & Memory Usage) a funguje nezávisle na šabloně.

| Oblast | Co dělá |
|---|---|
| Přihlášení | omezení pokusů (5 za 15 min → blokace IP na 30 min, další blokace 2× déle), obecné chybové hlášky, log přihlášení |
| Bezpečnost | vypnuté XML-RPC a pingbacky, zakázaný editor souborů, skrytý výčet uživatelů (`?author=`, REST, oEmbed), bezpečnostní HTTP hlavičky |
| SEO | sitemap bez uživatelů |
| Komentáře | úplně vypnuté včetně administrace a kanálů |
| Administrace | zjednodušené menu (úplné si administrátor zapne v profilu), Nástěnka bez novinek a rychlého konceptu, info o serveru v patičce |
| Aktualizace | WordPress, pluginy a překlady automaticky; šablona i plugin MND z GitHubu (nabídnou se, instalují se kliknutím) |
| Nástěnka | widget Zdraví webu: verze a aktualizace, velikost databáze, přihlášení, poslední údržba |

**Údržba webu** — *Nástroje → Údržba webu*
- Přehled systému (WordPress, PHP, databáze, verze šablony a pluginu)
- Úklid revizí, automatických konceptů, koše, prošlých transientů a osiřelých metadat
- Nepoužitá média (jen ke kontrole, nic se nemaže samo)
- Log přihlášení a rušení blokací

---

## Požadavky

- WordPress 6.5+ (testováno do 7.1)
- PHP 7.4+
- Polylang (volitelně, pro přepínání jazyků)

## Instalace

1. Stáhněte `mndgroup.zip` a `mndgroup-core.zip` z [posledního vydání](https://github.com/elvisek2020/wp-theme_mndgroup/releases/latest).
2. *Pluginy → Přidat nový → Nahrát plugin* → `mndgroup-core.zip` → Aktivovat.
3. *Vzhled → Motivy → Přidat nový → Nahrát motiv* → `mndgroup.zip` → Živý náhled → Aktivovat.

## Aktualizace

Šablona i plugin si nové vydání najdou samy. Stačí *Nástěnka → Aktualizace → Zkontrolovat znovu* a aktualizovat.

---

## Vývoj

```
theme/mndgroup/           šablona (podrobnosti v theme/mndgroup/README.md)
plugins/mndgroup-core/    plugin
mu-plugins/               pomůcky jen pro lokální vývoj — nenasazovat (v gitu nejsou)
dev/                      lokální WordPress v Dockeru a kontrola stránek (dev/README.md)
.github/workflows/        sestavení vydání
```

**Lokální prostředí** — WordPress v Dockeru na `http://localhost:8321`, šablona a plugin jsou do kontejneru připojené přímo z repozitáře (bez kopírování). Návod v [`dev/README.md`](dev/README.md).

Zálohy (`backup-*/`), přístupy a interní dokumenty (`docs/`) jsou v `.gitignore`.

## Vydání nové verze

Šablona i plugin mají společnou verzi.

1. Zvyšte `Version:` v `theme/mndgroup/style.css` **i** v `plugins/mndgroup-core/mndgroup-core.php` a doplňte [`CHANGELOG.md`](CHANGELOG.md).
2. Ověřte lokálně (`dev/tests` → `npm test`).
3. Commit a anotovaný tag:
   ```bash
   git add -A && git commit -m "X.Y.Z: …"
   git status                      # musí být čisto
   git tag -a vX.Y.Z -m "X.Y.Z" && git push && git push --tags
   ```
4. GitHub Action ověří, že tag odpovídá verzím, zkontroluje syntaxi PHP (i 7.4) a JS, sestaví `mndgroup.zip` a `mndgroup-core.zip` a vytvoří Release.

## Licence

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html) · © Zdeněk Král ([ElvisEK](https://www.elvisek.cz)) · písmo Montserrat: [SIL OFL 1.1](theme/mndgroup/assets/fonts/OFL.txt)
