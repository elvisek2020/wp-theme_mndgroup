# Changelog

## Nevydáno

## 2.1.0 — 2026-10-05
### Šablona
- Jednotný prefix: `mnd_` v PHP, `MND_` u konstant, `mnd-` u CSS tříd, proměnných a handlů skriptů – nekoliduje se styly pluginů
- `theme.json`: paleta odkazuje na `--mnd-*` proměnné, písmo Montserrat přes `fontFace` (WordPress ho načte na webu i v editoru), šířka obsahu
- `assets/css/main.css` s proměnnými nahoře v souboru, samostatný `print.css` (bez bannerů a navigace, adresy odkazů za textem)
- Aktualizace z GitHub Releases přes GitHub API (balíček `mndgroup.zip`), na lokálním vývoji vypnuté; šablona se už neaktualizuje sama, jen se nabídne
- Upozornění v administraci, když chybí nebo není aktivní plugin MND Group Core

### MND Group Core
- Nový doprovodný plugin, nahrazuje All-In-One Security, Simple Login Log, Admin Menu Editor a Server IP & Memory Usage
- **Přihlášení**: po 5 chybných pokusech za 15 min blokace IP na 30 min, další blokace dvojnásobně dlouhé (max. 24 h); obecné chybové hlášky; log přihlášení (200 událostí, max. 90 dní)
- **Bezpečnost**: vypnuté XML-RPC a pingbacky, zakázaný editor souborů, skrytý výčet uživatelů (`?author=`, REST, oEmbed), bezpečnostní hlavičky; sitemap bez uživatelů
- **Komentáře** úplně vypnuté včetně administrace, kanálů a widgetu
- **Administrace**: zjednodušené menu (skryté Nástroje, Nastavení, Jazyky), úplné menu se zapíná v profilu; Nástěnka bez novinek WordPress.org a rychlého konceptu; info o serveru v patičce
- **Aktualizace**: WordPress (i hlavní verze), pluginy a překlady automaticky; šablona a plugin MND jen z GitHub Releases kliknutím
- **Nástroje → Údržba webu**: přehled systému, úklid databáze, nepoužitá média, log přihlášení a rušení blokací; widget **Zdraví webu** na Nástěnce

### Vydání
- Repozitář ve struktuře `theme/`, `plugins/`, `dev/` (jako wp-theme_elvisek); vydání obsahuje `mndgroup.zip` a `mndgroup-core.zip`
- Release na `ubuntu-24.04`: kontrola verze šablony i pluginu, syntaxe PHP (i 7.4) a JS
- `dev/`: Docker prostředí (`setup.sh` ze zálohy, `wp-config.php` jen pro čtení) a kontrola stránek přes Playwright (`dev/tests`)

## 2.0.0 — 2026-10-05
### Šablona
- První verze nové šablony, nahrazuje „DP Enigmatic for MND Group“ (2018) – stejný vzhled, kód přepsaný od základu
- Funkční prezentace bannerů (původní padala na jQuery 3 a bannery se zobrazovaly pod sebou) – pauza, tečky, swipe, klávesnice; volitelně všechny pod sebou
- Bez jQuery a Slicku; ikony dlaždic 1 kB místo 765 kB; písmo Montserrat lokálně; responzivní obrázky
- Mobil a tablet: bannery i na mobilu, seznam společností se rozbalí šipkou
- Přístupnost: kontrast WCAG AA, ovládání klávesnicí, „omezit pohyb“
- Kompatibilní s obsahem webu – po aktivaci převezme menu v Polylangu a uklidí widgety
- Nastavení v Přizpůsobení (režim bannerů, interval, logo KKCG, Google Analytics 4)
- Aktualizace z GitHub Releases
