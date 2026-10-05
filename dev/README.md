# Lokální vývoj

Požadavek: Docker (Docker Desktop / OrbStack) a záloha webu v `../backup-RRRR-MM-DD/`
(`files/` = webroot, `db/` = dump databáze).

```bash
cd dev
./setup.sh              # první spuštění (kopie webrootu, import DB, přepis URL, aktivace šablony a pluginu)
docker compose up -d    # další spuštění
docker compose down     # zastavení (data zůstanou)
docker compose down -v  # smazání DB → příští setup.sh naimportuje znovu
docker compose run --rm cli wp <příkaz>   # WP-CLI
```

- Web: http://localhost:8321, přihlášení lokálního správce v `dev/.local-admin` (heslo se generuje při `setup.sh`).
- Šablona se vyvíjí v `../theme/mndgroup`, plugin v `../plugins/mndgroup-core` (připojeno do kontejneru, změny jsou vidět hned).
- mu-pluginy v `../mu-plugins` (jen pro vývoj, v gitu nejsou).
- `wp/` je kopie webrootu ze zálohy, `db/` dump pro první import; `wp-config.php` je lokální a připojený jen pro čtení.
- Pluginy, které nahrazuje šablona nebo MND Group Core, přesune `setup.sh` do `_disabled-plugins/` (pro porovnání chování stačí složku vrátit do `wp/wp-content/plugins/`). `_incoming/` je pracovní složka pro stažené balíčky. Obojí je mimo git.
- Debug log: `wp/wp-content/debug.log`.

## Kontrola před vydáním

```bash
cd dev/tests
npm install   # jen poprvé (playwright-core, prohlížeč se nestahuje)
npm test      # stránky na 1400 a 390 px, konzole, přetékání, bannery, dlaždice, zabezpečení, SEO, /llms.txt, cookie lišta
```

Používá nainstalovaný Chromium/Chrome (jiný přes `CHROME_PATH`), snímky ukládá do `tests/out/`.

Cookie lišta se testuje jen s vyplněným ID GA4 (lokálně stačí testovací `G-TEST000001` v Přizpůsobení); požadavky na Google test zachytí a nikam nepošle.
