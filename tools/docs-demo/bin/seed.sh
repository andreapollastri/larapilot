#!/usr/bin/env bash
# The database and the runtime records of Fernway: tables and rows, then a weekend of traffic through
# the app's own routes (logs, mail, queue, failed jobs, dumps), then the anonymization map.
#
#   tools/docs-demo/bin/seed.sh <target-dir> [port]     (port: a free one for a temporary server, 8849)
#
# Run after history.py. The traffic needs every class of the app, also those still on unmerged
# branches (the waitlist of US-006), the way Sam ran it on his machine: the full source is copied over
# the working tree for the run and the checked-out branch is put back afterwards (.larapilot/ kept).
set -euo pipefail
KIT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET="$(cd "${1:?usage: seed.sh <target-dir> [port]}" && pwd -P)"
PORT="${2:-8849}"
APP="$TARGET/fernway"
export DEMO_SHIFT_DAYS="$(cat "$TARGET/work/demo-shift")"

if lsof -nP -iTCP:"$PORT" -sTCP:LISTEN >/dev/null 2>&1; then
  echo "Port $PORT is taken: pass a free one." >&2; exit 2
fi

rm -rf "$TARGET/state-seed" && cp -R "$APP/.larapilot" "$TARGET/state-seed"
rsync -a --exclude .versions --exclude .gitignore --exclude composer.json --exclude composer.lock --exclude README.md "$KIT/fernway/" "$APP/"

rm -f "$APP/database/database.sqlite" "$APP/storage/logs/"*.log
rm -rf "$APP/storage/larapilot"
touch "$APP/database/database.sqlite"
DEMO_NOW="$(python3 -c "from datetime import datetime,timedelta;print((datetime.fromisoformat('2026-10-09T07:00:00+00:00')+timedelta(days=$DEMO_SHIFT_DAYS)).isoformat())")" \
  "$KIT/bin/tb" "$TARGET" fernway:demo-seed

MAIL_MAILER=array "$KIT/bin/serve.sh" "$TARGET" "$PORT" local >/dev/null
trap 'kill "$(cat "$TARGET/serve-$PORT.pid")" 2>/dev/null || true' EXIT
python3 "$KIT/bin/traffic.py" "$TARGET" "$PORT"
kill "$(cat "$TARGET/serve-$PORT.pid")" && rm -f "$TARGET/serve-$PORT.pid"
trap - EXIT

# Back to the branch Sam has checked out, with the backlog as the story left it.
cd "$APP"
git checkout -q -f -- .
git clean -q -fd -- app routes resources database/migrations tests
rsync -a --delete "$TARGET/state-seed/" "$APP/.larapilot/"
rm -rf "$TARGET/state-seed"

python3 "$KIT/bin/retime.py" "$TARGET"
python3 "$KIT/bin/anonymization.py" "$TARGET"
echo "seeded: $(sqlite3 "$APP/database/database.sqlite" 'select count(*) from bookings') bookings, $(wc -l < "$APP/storage/logs/laravel.log") log lines"
