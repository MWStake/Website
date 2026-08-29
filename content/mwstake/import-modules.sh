#!/usr/bin/env bash
# Import the committed Scribunto (Lua) module dump into the dev wiki.
# Usage: ./import-modules.sh [web-container-name]
set -euo pipefail
WEB="${1:-mwstake-web-1}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
XML="$SCRIPT_DIR/modules.xml"

echo ">> Copying $XML into $WEB ..."
podman cp "$XML" "$WEB:/tmp/modules.xml"

echo ">> Running importDump.php ..."
podman exec -it "$WEB" bash -c "cd /var/www/mediawiki/w/maintenance && php importDump.php /tmp/modules.xml" || true

echo ">> Forcing Scribunto content model on Module: pages ..."
podman cp "$SCRIPT_DIR/fix-scribunto-model.php" "$WEB:/var/www/mediawiki/w/maintenance/fix-scribunto-model.php"
podman exec -it "$WEB" bash -c "cd /var/www/mediawiki/w/maintenance && php fix-scribunto-model.php" || true

echo ">> Done. Purge cached pages or restart web + varnish, e.g.:"
echo "   podman restart $WEB mwstake-varnish-1"
