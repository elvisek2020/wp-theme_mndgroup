# Změny

Šablona a plugin mají vždy stejnou verzi, tag `vX.Y.Z` vydává obojí najednou.

## Nevydáno

## 2.1.0 – 2026-10-05

### Šablona
- CSS třídy a proměnné s jednotným prefixem `mnd-` – nekolidují se styly pluginů.
- `theme.json`: paleta, písmo a šířka obsahu pro blokový editor; barvy odkazují na CSS
  proměnné v novém `tokens.css`, takže editor i web používají stejné hodnoty.
- Samostatný `print.css` (bez bannerů a navigace, adresy odkazů za textem).
- Moduly v `functions.php` jako seznam s komentáři – vypínají se zakomentováním řádku.
- Updater se na lokálním vývoji nespouští (`MNDGROUP_UPDATER_ON_LOCAL` pro test).
- Upozornění v administraci, když chybí nebo není aktivní plugin mndgroup-core.

### Plugin mndgroup-core (nový)
- Ochrana přihlášení: po 5 chybných pokusech za 15 min blokace IP na 30 min, další blokace
  dvojnásobně dlouhé (max. 24 h); obecné chybové hlášky; záznam přihlášení.
- Zabezpečení: vypnuté XML-RPC a pingbacky, zakázaný editor souborů, skrytý výčet uživatelů
  (`?author=`, REST, oEmbed), sitemap bez uživatelů, bezpečnostní hlavičky.
- Komentáře úplně vypnuté včetně administrace, kanálů a widgetu.
- Zjednodušené menu administrace (skryté Nástroje, Nastavení, Jazyky); úplné menu se zapíná
  v profilu. Nástěnka bez novinek WordPress.org a rychlého konceptu.
- Nástroje → Údržba webu: přehled systému, úklid databáze, nepoužitá média, záznam přihlášení,
  rušení blokací. Widget „MND Group – stav webu“ na Nástěnce.
- Aktualizace z GitHub Releases, po aktivaci zapne automatické aktualizace pluginu i šablony.
- Nahrazuje pluginy All-In-One Security, Simple Login Log, Admin Menu Editor a Server IP & Memory Usage.

### Vydání
- Repozitář rozdělený na `theme/`, `plugins/` a `dev/`; vydání obsahuje `mndgroup.zip`,
  `mndgroup-core.zip` a `update.json` (s balíčkem šablony i pluginu).
- CI na `ubuntu-24.04`: syntaxe PHP 7.4 i 8.4, syntaxe JS, shoda verzí šablony a pluginu.
- `dev/`: Docker prostředí se `setup.sh` (import zálohy, přepis adres, lokální správce)
  a kontrola stránek přes Playwright.

## 2.0.0 – 2026-10-05

První verze nové šablony, nahrazuje „DP Enigmatic for MND Group“ (2018).

- Stejný vzhled, kód přepsaný od základu: bez jQuery a Slicku, moderní CSS, vlastní JS (7 kB).
- Funkční prezentace bannerů (původní padala na jQuery 3) – pauza, tečky, swipe, klávesnice;
  volitelně všechny bannery pod sebou.
- Ikony dlaždic 1 kB místo 765 kB, písmo Montserrat hostované lokálně, responzivní obrázky.
- Mobil a tablet: bannery i na mobilu, seznam společností se rozbalí šipkou.
- Přístupnost: kontrast WCAG AA, ovládání klávesnicí, „omezit pohyb“.
- Kompatibilní s obsahem webu – po aktivaci převezme menu v Polylangu a uklidí widgety.
- Nastavení v Přizpůsobení (režim bannerů, interval, logo KKCG, Google Analytics 4).
- Aktualizace z GitHubu (vydání přes GitHub Actions), automatické aktualizace zapnuté po aktivaci.
