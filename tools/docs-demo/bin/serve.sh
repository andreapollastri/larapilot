#!/usr/bin/env bash
# Serves the Fernway app: serve.sh <target-dir> <port> [APP_ENV]   (APP_ENV: local by default)
# Writes the pid to <target-dir>/serve-<port>.pid and the output to <target-dir>/serve-<port>.log.
set -euo pipefail
KIT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET="$(cd "${1:?usage: serve.sh <target-dir> <port> [env]}" && pwd -P)"
PORT="${2:?port}"
ENVIRONMENT="${3:-local}"
if lsof -nP -iTCP:"$PORT" -sTCP:LISTEN >/dev/null 2>&1; then
  echo "Port $PORT is taken: pick another (lsof -nP -iTCP -sTCP:LISTEN)." >&2; exit 2
fi
export TESTBENCH_WORKING_PATH="$TARGET/work" APP_ENV="$ENVIRONMENT"
# The server runs on the real clock: PHP's own DateTimeImmutable cannot be frozen (see README).
cd "$TARGET/fernway"
nohup php -d memory_limit=1G -d auto_prepend_file="$TARGET/work/prepend.php" -S 127.0.0.1:"$PORT" -t public server.php \
  >"$TARGET/serve-$PORT.log" 2>&1 &
echo $! > "$TARGET/serve-$PORT.pid"
sleep 1
echo "http://127.0.0.1:$PORT/larapilot ($ENVIRONMENT, pid $(cat "$TARGET/serve-$PORT.pid"))"
