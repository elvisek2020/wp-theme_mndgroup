# Lokální vývoj

Docker kopie webu (WordPress + MariaDB 10.11 + WP-CLI) na http://localhost:8321.
Šablona (`../theme/mndgroup`) a plugin (`../plugins/mndgroup-core`) se připojují
přímo z repozitáře, nic se nekopíruje.

```bash
./setup.sh ~/cesta/k/zaloze/files ~/cesta/k/dump.sql   # poprvé (nebo znovu = čistá DB ze zálohy)
docker compose up -d                                   # spustit
./wp.sh plugin list                                    # WP-CLI
docker compose down                                    # zastavit (DB zůstane ve volume)
```

- `dev/wp/` – soubory webu ze zálohy s lokálním `wp-config.php` (`WP_ENVIRONMENT_TYPE=local`,
  vypnutý WP-Cron a automatické aktualizace). V gitu není.
- Přihlášení lokálního správce je v `dev/.local-admin` (v gitu není, heslo se generuje při setupu).
- Zálohy a dumpy do repozitáře nepatří – drž je mimo jeho složku.

## Testy

```bash
cd tests && npm install          # jen poprvé (playwright-core, prohlížeč se nestahuje)
npm test                         # kontrola stránek na 1400 a 390 px
```

Skript používá nainstalovaný Chromium/Chrome (cestu jde změnit proměnnou `CHROME_PATH`)
a ukládá snímky do `tests/out/`. Kontroluje stavové kódy, chyby v konzoli, přetékání
stránky do strany na mobilu, prezentaci bannerů, dlaždice a bezpečnostní hlavičky.
