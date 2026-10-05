# Changelog

## Nevydáno

## 2.3.0 — 2026-10-05
### Šablona
- **SEO**: meta description ze stručného výpisu stránky (Stránky → Upravit → Stručný výpis, jinak začátek textu), Open Graph s jazykovými verzemi z Polylangu, karta pro X a strukturovaná data organizace a webu; obrázek pro sdílení s logem MND. S aktivním SEO pluginem (Yoast, Rank Math…) se nevypisuje nic, aby značky nebyly dvakrát
- **Google Analytics 4 s cookie lištou**: bez vyplněného ID se nenačte nic. S ID uvidí návštěvník lištu Přijmout / Odmítnout a Google se načte až po souhlasu (Consent Mode v2, výchozí stav „odmítnuto“). Volba platí 12 měsíců, změnit jde odkazem „Nastavení cookies“ v patičce, při odvolání se smažou cookies `_ga`. Přihlášení uživatelé se neměří
- ID měření se při prvním otevření administrace samo převezme z MonsterInsights – po jeho vypnutí se měří dál bez dalšího nastavování; dokud je MonsterInsights aktivní, šablona GA nevkládá (neměřilo by se dvakrát)

### MND Group Core
- **Obrázky jako WebP**: nahrané JPG a PNG se uloží rovnou jako WebP (fotky otočené podle EXIF), zmenšeniny také ve WebP – i při nahrání přes REST API a WP-CLI
- **Údržba webu → Obrázky na WebP**: jednorázový převod starších obrázků včetně všech velikostí a odkazů v obsahu; staré adresy přesměrují (301), originály jdou do karantény
- **Údržba webu → Databáze**: velikost tabulek, autoload a největší volby, optimalizace tabulek; **Média a odkazy**: největší soubory, nepoužité obrázky, rozbité interní odkazy
- **Úklid po odebraných pluginech**: najde tabulky, volby a složky po AIOS, Simple Login Log, BackUpWordPress, MonsterInsights, Huge IT Lightbox, Admin Menu Editor, Admin Columns, Simple Custom Post Order, W3 Total Cache a staré šabloně. Jedním tlačítkem je přesune do karantény (volby se zálohují do JSON, tabulky se přejmenují), karanténa se maže samostatně
- **Sitemap s datem poslední změny** a **/llms.txt** – stručný přehled webu a stránek obou jazyků pro AI vyhledávače; obojí jde vypnout v Nastavení
- **Zdraví webu** přehledněji: verze a dostupné aktualizace, velikost databáze, autoload a uploads (přepočet tlačítkem Obnovit), koncepty, neúspěšná přihlášení, blokace, poslední přihlášení a poslední údržba
- **Log přihlášení** má vlastní stránku Nástroje → Log přihlášení, ukládá i prohlížeč; v přehledu uživatelů sloupec Poslední přihlášení
- Adresy `?author=…` a archivy autorů přesměrují na úvodní stránku (dřív 404); v patičce administrace i IP serveru
- Nastavení: nové sekce SEO a Média

### Nástroje
- `tools/wp.py` – REST klient pro web (stránky, slidy, média, koncepty; publikuje vždy člověk), přístup v `ctime.txt`
- `tools/img.py` – příprava obrázků do WebP (výchozí rozměr banneru 1920 × 484)

### Vydání
- Struktura repozitáře podle skillu: `.gitignore` podle vzoru, `tools/`, v `dev/` složky `_disabled-plugins/` (nahrazené pluginy) a `_incoming/`, `theme/mndgroup/.gitkeep` (do ZIPu se nebalí)
- Kontrola stránek (`dev/tests`) navíc ověřuje SEO značky, `/llms.txt` a cookie lištu (bez souhlasu žádný požadavek na Google)

## 2.2.0 — 2026-10-05
### MND Group Core
- **Nastavení → MND Group Core**: každou funkci jde zapnout a vypnout – omezení pokusů o přihlášení (počet pokusů, délka blokace), log přihlášení, XML-RPC, editor souborů, skrytí uživatelských jmen a verze WordPressu, bezpečnostní hlavičky, HSTS, sitemap bez uživatelů, komentáře, info o serveru v patičce, widget Zdraví webu
- Automatické aktualizace podle nastavení: WordPress všechny verze / jen opravné / vypnuto, pluginy a šablony všechny / podle volby u jednotlivých položek; šablona a plugin MND vždy ručně
- Odebrána zjednodušená administrace (skrývání položek menu a úklid Nástěnky) – menu je zase úplné
- Výchozí nastavení odpovídá verzi 2.1, aktualizace na webu nic nemění; odkaz Nastavení v přehledu pluginů, v Údržbě webu a ve widgetu

### Šablona
- Po přepnutí šablony se z Polylangu převezme přiřazení menu pro všechny jazyky – doplní se i jazyky, které WordPress při přepnutí přes administraci neuložil (anglická verze byla bez dlaždic)

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
