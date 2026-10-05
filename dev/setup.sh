#!/usr/bin/env bash
# Jednorázové spuštění lokálního webu ze zálohy (../backup-*/: files/ = webroot, db/ = dump).
#   ./setup.sh                       nejnovější ../backup-*/
#   ./setup.sh ../backup-2026-10-05  konkrétní záloha
set -euo pipefail
cd "$(dirname "$0")"

BACKUP="${1:-$(ls -d ../backup-*/ 2>/dev/null | sort | tail -1)}"
BACKUP="${BACKUP%/}"
if [ ! -d "$BACKUP/files" ] || [ ! -d "$BACKUP/db" ]; then
	echo "Chybí záloha se složkami files/ a db/ – zadejte cestu: ./setup.sh ../backup-RRRR-MM-DD"
	exit 1
fi

if [ ! -d wp ]; then
	echo "▶ Kopíruju webroot ze zálohy $BACKUP…"
	cp -cR "$BACKUP/files" wp 2>/dev/null || cp -R "$BACKUP/files" wp
	# Šablona a plugin se připojují z repozitáře.
	rm -rf wp/wp-content/themes/mndgroup wp/wp-content/plugins/mndgroup-core
fi
if [ ! -d db ]; then
	echo "▶ Připravuju dump databáze…"
	mkdir db
	cp "$BACKUP"/db/*.sql db/
fi
mkdir -p ../mu-plugins

echo "▶ Startuju DB a WordPress…"
docker compose up -d

echo "▶ Čekám na import databáze…"
until docker compose exec -T db mariadb -uwp -pwp mndgroup -e "SELECT 1 FROM wp_options LIMIT 1" >/dev/null 2>&1; do sleep 3; done

WP="docker compose run --rm cli wp"
echo "▶ Přepisuju URL na localhost…"
for url in https://www.mndgroup.eu http://www.mndgroup.eu https://mndgroup.eu http://mndgroup.eu; do
	$WP search-replace "$url" 'http://localhost:8321' --all-tables --skip-columns=guid --report-changed-only
done
$WP transient delete --all

echo "▶ Aktivuju šablonu a plugin z repozitáře…"
$WP theme activate mndgroup
$WP plugin activate mndgroup-core

echo "▶ Vypínám ManageWP a pluginy, které nahrazuje šablona nebo MND Group Core…"
OBSOLETE="lightbox mce-table-buttons server-ip-memory-usage simple-custom-post-order simple-login-log \
	google-analytics-for-wordpress codepress-admin-columns backupwordpress all-in-one-wp-security-and-firewall \
	admin-menu-editor w3-total-cache"
$WP plugin deactivate worker $OBSOLETE || true
# Nahrazené pluginy mimo web do _disabled-plugins/ – pro porovnání chování stačí složku vrátit.
mkdir -p _disabled-plugins _incoming
for p in $OBSOLETE; do
	[ -d "wp/wp-content/plugins/$p" ] || continue
	if [ -e "_disabled-plugins/$p" ]; then
		rm -rf "wp/wp-content/plugins/$p"
	else
		mv "wp/wp-content/plugins/$p" _disabled-plugins/
	fi
done
curl -s -o /dev/null http://localhost:8321/   # dokončí přepnutí šablony (menu v Polylangu, widgety)
$WP cache flush || true

echo "▶ Lokální správce (devlocal)…"
PASS=$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-20)
if $WP user get devlocal --field=ID >/dev/null 2>&1; then
	$WP user update devlocal --user_pass="$PASS" --quiet
else
	$WP user create devlocal devlocal@example.test --role=administrator --user_pass="$PASS" --quiet
fi
printf 'url=http://localhost:8321/wp-admin\nuser=devlocal\npass=%s\n' "$PASS" > .local-admin
chmod 600 .local-admin

echo
echo "✅ Hotovo: http://localhost:8321  (admin: http://localhost:8321/wp-admin, přihlášení v dev/.local-admin)"
