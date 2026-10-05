# MND Group – jádro webu

Doprovodný plugin šablony [MND Group](../../theme/mndgroup/README.md). Obsahuje funkce, které
mají přežít případnou výměnu šablony. Verze je vždy stejná jako verze šablony.

| Modul | Co dělá | Nahrazuje |
|---|---|---|
| `login-protection` | po 5 chybných pokusech za 15 min zablokuje IP na 30 min (každá další blokace 2× déle, max. 24 h); obecné chybové hlášky; záznam přihlášení | AIOS, Simple Login Log |
| `hardening` | vypnuté XML-RPC a pingbacky, zákaz editoru souborů, bez výčtu uživatelů (`?author=`, REST, oEmbed), sitemap bez uživatelů, bezpečnostní hlavičky (HSTS, nosniff, X-Frame-Options, Referrer-Policy, Permissions-Policy) | AIOS |
| `comments` | komentáře a pingbacky úplně vypnuté včetně administrace a kanálů | – |
| `admin-menu` | zjednodušené menu (skryté Nástroje, Nastavení, Jazyky); úplné menu si administrátor zapne v profilu; Nástěnka bez novinek a rychlého konceptu | Admin Menu Editor |
| `maintenance` | Nástroje → Údržba webu (přehled systému, úklid DB, nepoužitá média, záznam přihlášení a rušení blokací), widget „MND Group – stav webu“ na Nástěnce | Server IP & Memory Usage |
| `updates` | aktualizace z GitHub Releases, po aktivaci zapne automatické aktualizace pluginu i šablony | – |

Modul se vypne zakomentováním řádku v `mndgroup-core.php`.

## Nastavení (volitelně v `wp-config.php`)

```php
define( 'MNDGROUP_CORE_LOGIN_ATTEMPTS', 5 );     // počet pokusů před blokací
define( 'MNDGROUP_UPDATER_ON_LOCAL', true );     // hledat aktualizace i na lokálním vývoji
```

Filtry: `mndgroup_core_client_ip` (IP za reverzní proxy), `mndgroup_core_allow_xmlrpc`,
`mndgroup_core_security_headers`, `mndgroup_core_hidden_menu_items`.
