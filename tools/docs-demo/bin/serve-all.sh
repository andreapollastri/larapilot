#!/usr/bin/env bash
# Starts what the screenshots need: serve-all.sh <target-dir> [local-port] [staging-port] [hackly-port]
#   local    APP_ENV=local, the dashboard open (implicit admin) — most pages
#   staging  APP_ENV=staging, behind the sign-in — sign-in, Account, Users, the roles
#   hackly   php/hackly-stub.php, the scanner the Security page reads
# stop.sh <target-dir> stops the three.
set -euo pipefail
KIT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET="$(cd "${1:?usage: serve-all.sh <target-dir>}" && pwd -P)"
LOCAL="${2:-8841}"; STAGING="${3:-8842}"; HACKLY="${4:-8843}"
"$KIT/bin/serve.sh" "$TARGET" "$LOCAL" local
"$KIT/bin/serve.sh" "$TARGET" "$STAGING" staging
if lsof -nP -iTCP:"$HACKLY" -sTCP:LISTEN >/dev/null 2>&1; then echo "Port $HACKLY is taken." >&2; exit 2; fi
nohup php -S 127.0.0.1:"$HACKLY" "$KIT/php/hackly-stub.php" >"$TARGET/serve-$HACKLY.log" 2>&1 &
echo $! > "$TARGET/serve-$HACKLY.pid"
echo "hackly stub on http://127.0.0.1:$HACKLY (pid $(cat "$TARGET/serve-$HACKLY.pid"))"
