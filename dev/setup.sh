#!/bin/sh
# Založí lokální kopii webu ze zálohy:
#   ./setup.sh <složka se zálohou souborů webu> <dump databáze .sql>
# Zkopíruje soubory do dev/wp (jen poprvé), zapíše lokální wp-config.php,
# naimportuje databázi, přepíše adresy na localhost, aktivuje šablonu a plugin
# z repozitáře, vypne pluginy, které nahrazují, a založí lokální účet správce.
set -eu
cd "$(dirname "$0")"

FILES="${1:?Použití: ./setup.sh <složka se zálohou souborů> <dump.sql>}"
DUMP="${2:?Použití: ./setup.sh <složka se zálohou souborů> <dump.sql>}"
URL="http://localhost:8321"
PROD_URLS="https://www.mndgroup.eu http://www.mndgroup.eu https://mndgroup.eu http://mndgroup.eu"

# Pluginy, které nahrazuje šablona nebo plugin mndgroup-core (viz CHANGELOG 2.1.0).
OBSOLETE="lightbox mce-table-buttons server-ip-memory-usage simple-custom-post-order simple-login-log google-analytics-for-wordpress codepress-admin-columns backupwordpress all-in-one-wp-security-and-firewall admin-menu-editor"

if [ ! -d wp ]; then
	echo "→ Kopíruji soubory webu do dev/wp"
	cp -cR "$FILES" wp 2>/dev/null || cp -R "$FILES" wp
	# Šablona a plugin se připojují z repozitáře.
	rm -rf wp/wp-content/themes/mndgroup wp/wp-content/plugins/mndgroup-core
fi

echo "→ Lokální wp-config.php"
cat > wp/wp-config.php <<'PHP'
<?php
/**
 * Lokální konfigurace (dev/setup.sh). Na produkci se nepoužívá.
 */
define( 'DB_NAME', 'mndgroup' );
define( 'DB_USER', 'mndgroup' );
define( 'DB_PASSWORD', 'mndgroup' );
define( 'DB_HOST', 'db' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'AUTH_KEY', 'local' );
define( 'SECURE_AUTH_KEY', 'local' );
define( 'LOGGED_IN_KEY', 'local' );
define( 'NONCE_KEY', 'local' );
define( 'AUTH_SALT', 'local' );
define( 'SECURE_AUTH_SALT', 'local' );
define( 'LOGGED_IN_SALT', 'local' );
define( 'NONCE_SALT', 'local' );

$table_prefix = 'wp_';

define( 'WP_HOME', 'http://localhost:8321' );
define( 'WP_SITEURL', 'http://localhost:8321' );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISABLE_WP_CRON', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
PHP

echo "→ Startuji kontejnery"
docker compose up -d --wait db wp

echo "→ Import databáze"
docker compose exec -T db mariadb -uroot -proot -e "DROP DATABASE IF EXISTS mndgroup; CREATE DATABASE mndgroup CHARACTER SET utf8mb4; GRANT ALL ON mndgroup.* TO 'mndgroup'@'%';"
docker compose exec -T db mariadb -umndgroup -pmndgroup mndgroup < "$DUMP"

echo "→ Adresy na $URL"
for prod in $PROD_URLS; do
	./wp.sh search-replace "$prod" "$URL" --all-tables --skip-columns=guid --quiet
done
./wp.sh transient delete --all

echo "→ Šablona a plugin z repozitáře"
./wp.sh theme activate mndgroup
./wp.sh plugin activate mndgroup-core
./wp.sh plugin deactivate $OBSOLETE || true
# Dokončí přepnutí šablony (převzetí menu v Polylangu, úklid widgetů).
curl -s -o /dev/null "$URL/"

echo "→ Lokální účet správce"
PASS=$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-20)
if ./wp.sh user get devlocal --field=ID >/dev/null 2>&1; then
	./wp.sh user update devlocal --user_pass="$PASS" --quiet
else
	./wp.sh user create devlocal devlocal@example.test --role=administrator --user_pass="$PASS" --quiet
fi
printf 'url=%s/wp-login.php\nuser=devlocal\npass=%s\n' "$URL" "$PASS" > .local-admin
chmod 600 .local-admin

echo "Hotovo: $URL (přihlášení v dev/.local-admin)"
