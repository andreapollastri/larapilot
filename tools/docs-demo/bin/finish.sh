#!/usr/bin/env bash
# The last touches of the Fernway demo: the dashboard users, the API token, the scanner's address,
# and the time of the log file.
#
#   tools/docs-demo/bin/finish.sh <target-dir> [hackly-port]   (hackly-port: where hackly-stub.php will answer, 8843)
set -euo pipefail
KIT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO="$(cd "$KIT/../.." && pwd)"
TARGET="$(cd "${1:?usage: finish.sh <target-dir>}" && pwd -P)"
HACKLY_PORT="${2:-8843}"
APP="$TARGET/fernway"
TB="$KIT/bin/tb"
SHIFT="$(cat "$TARGET/work/demo-shift")"
at() { python3 -c "from datetime import datetime,timedelta;print((datetime.fromisoformat('$1')+timedelta(days=$SHIFT)).isoformat())"; }

# The team on the dashboard of the shared staging host: one person per role.
DEMO_NOW="$(at 2026-10-05T11:20:00+00:00)" "$TB" "$TARGET" larapilot:dashboard-user add maya --role=admin --password=fernway-demo-maya >/dev/null
DEMO_NOW="$(at 2026-10-05T11:22:00+00:00)" "$TB" "$TARGET" larapilot:dashboard-user add luca --role=dev --password=fernway-demo-luca >/dev/null
DEMO_NOW="$(at 2026-10-05T11:23:00+00:00)" "$TB" "$TARGET" larapilot:dashboard-user add sam --role=dev --password=fernway-demo-sam >/dev/null
DEMO_NOW="$(at 2026-10-05T11:25:00+00:00)" "$TB" "$TARGET" larapilot:dashboard-user add priya --role=pm --password=fernway-demo-priya >/dev/null
DEMO_NOW="$(at 2026-10-05T11:31:00+00:00)" "$TB" "$TARGET" larapilot:dashboard-user add erin --role=ext --password=fernway-demo-erin >/dev/null
DEMO_NOW="$(at 2026-10-05T11:40:00+00:00)" "$TB" "$TARGET" larapilot:api-token generate >/dev/null
DEMO_SHIFT_DAYS="$SHIFT" "$TB" "$TARGET" fernway:demo-team

# Hackly, the scanner settings-set associated on 5 October: php/hackly-stub.php answers its API on this
# machine. *.test resolves to 127.0.0.1 where Laravel Herd or Valet runs dnsmasq; elsewhere use 127.0.0.1.
python3 - "$APP/.env" "$HACKLY_PORT" <<'PY'
import sys
path, port = sys.argv[1], sys.argv[2]
lines = [l for l in open(path).read().splitlines() if not l.startswith("LARAPILOT_HACKLY_")]
lines += [f"LARAPILOT_HACKLY_URL=http://hackly.fernway.test:{port}", "LARAPILOT_HACKLY_TOKEN=hk_demo_fernway_not_a_real_token"]
open(path, "w").write("\n".join(lines) + "\n")
PY

# The dependencies checked against OSV.dev on Sunday night (needs the network; skipped offline).
DEMO_NOW="$(at 2026-10-11T23:19:00+00:00)" "$TB" "$TARGET" larapilot:vendor-audit >/dev/null 2>&1 || echo "vendor-audit skipped (offline?)"

# Files the File manager lists keep the day they arrived, not the minute of the build.
stamp() { python3 -c "from datetime import datetime,timedelta;print((datetime.fromisoformat('$1')+timedelta(days=$SHIFT)).strftime('%Y%m%d%H%M'))"; }
find "$APP/.larapilot/design-systems" -exec touch -t "$(stamp 2026-09-07T08:45)" {} +
find "$APP/.larapilot/brand" "$APP/.larapilot/client-materials" "$APP/.larapilot/legacy" -exec touch -t "$(stamp 2026-09-07T08:48)" {} +
find "$APP/.larapilot" -name '*.lock' -delete

# choices.yaml is stamped with PHP's own clock: put it back on the morning of the inception.
python3 - "$APP/.larapilot/choices.yaml" "$(at 2026-09-07T11:15:00+00:00)" <<'PY'
import re, sys
path, when = sys.argv[1], sys.argv[2]
text = open(path).read()
open(path, "w").write(re.sub(r"(?m)^updated_at: .*$", f"updated_at: '{when}'", text))
PY

# The log was last written on Sunday evening.
touch -t "$(python3 -c "from datetime import datetime,timedelta;print((datetime(2026,10,11,20,28)+timedelta(days=$SHIFT)).strftime('%Y%m%d%H%M'))")" "$APP/storage/logs/laravel.log"
echo "finished: users maya (admin), luca and sam (dev), priya (pm), erin (ext); passwords fernway-demo-<name>"
