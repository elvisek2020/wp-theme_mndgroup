# MND Group – šablona a plugin pro www.mndgroup.eu

Vlastní WordPress šablona rozcestníku skupiny MND a doprovodný plugin. Bez jQuery,
bez build kroku a bez externích služeb; aktualizuje se sama z GitHub Releases.

| | |
|---|---|
| [`theme/mndgroup`](theme/mndgroup/README.md) | šablona – vzhled, bannery, dlaždice s firmami, Polylang (CZ/EN) |
| [`plugins/mndgroup-core`](plugins/mndgroup-core/README.md) | plugin – ochrana přihlášení, zabezpečení, vypnuté komentáře, zjednodušená administrace, údržba webu |
| [`dev/`](dev/README.md) | lokální vývoj v Dockeru, kontrola stránek Playwrightem |
| [`CHANGELOG.md`](CHANGELOG.md), [`WISHLIST.md`](WISHLIST.md) | změny po verzích, nápady na později |

Šablona a plugin mají vždy **stejnou verzi** a vydávají se jedním tagem.

## Instalace na web

1. Stáhnout `mndgroup.zip` a `mndgroup-core.zip` z [posledního vydání](https://github.com/elvisek2020/wp-theme_mndgroup/releases/latest).
2. Pluginy → Instalace pluginů → Nahrát plugin → `mndgroup-core.zip` → aktivovat.
3. Vzhled → Šablony → Přidat → Nahrát šablonu → `mndgroup.zip` → *Živý náhled* → aktivovat.

Po aktivaci se zapnou automatické aktualizace šablony i pluginu; další verze si web
nainstaluje sám (kontrola 2× denně, ručně Nástěnka → Aktualizace → Zkontrolovat znovu).

## Vydání nové verze

1. Zvýšit verzi na třech místech: `Version` v `theme/mndgroup/style.css`, `Version`
   a `MNDGROUP_CORE_VERSION` v `plugins/mndgroup-core/mndgroup-core.php`.
2. Doplnit `CHANGELOG.md` (nahoře nová verze, sekce Šablona / Plugin / Vydání).
3. Ověřit lokálně (`dev/`, `npm test`), commit a push do `main` – CI zkontroluje PHP 7.4 i 8.4,
   JS a shodu verzí.
4. Tag až po commitu:
   ```bash
   git status
   git tag vX.Y.Z && git push origin vX.Y.Z
   ```
5. GitHub Actions sestaví `mndgroup.zip`, `mndgroup-core.zip` a `update.json` a vytvoří vydání.

Na weby se dostane jen otagovaná verze – samotný push do `main` nic nenasazuje. Když tag
skončí na špatném commitu, nepřesouvat ho – zvednout verzi a vydat znovu. Změny jen v CI
se nevydávají (v CHANGELOGu sekce „Nevydáno“).

## Požadavky

WordPress 6.5+, PHP 7.4+. Polylang je volitelný.
