#!/bin/sh
# WP-CLI nad lokální kopií, např. ./wp.sh plugin list
# Výstup se zbaví hlášek PHP Deprecated/Notice ze starých pluginů, návratový kód zůstává.
cd "$(dirname "$0")" || exit 1
out=$(docker compose run --rm -T cli wp "$@" 2>&1)
code=$?
if [ -n "$out" ]; then
	printf '%s\n' "$out" | grep -v -E "^(Deprecated|Notice|PHP Deprecated|PHP Notice)| Container " || true
fi
exit $code
