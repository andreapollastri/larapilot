#!/usr/bin/env bash
# Rebuilds the Fernway demo from nothing: build.sh [target-dir]
# (default: $TMPDIR/fernway-demo — never inside the repository). About 4 minutes.
set -euo pipefail
KIT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET="${1:-${TMPDIR:-/tmp}/fernway-demo}"
mkdir -p "$TARGET"
[ -x "$KIT/bin/stop.sh" ] && "$KIT/bin/stop.sh" "$TARGET"
"$KIT/bin/setup-app.sh" "$TARGET"
"$KIT/bin/tb" "$TARGET" larapilot:install --skip-composer >/dev/null
python3 "$KIT/bin/history.py" "$TARGET"
"$KIT/bin/agents.sh" "$TARGET"
"$KIT/bin/seed.sh" "$TARGET"
"$KIT/bin/finish.sh" "$TARGET"
echo "built: $TARGET — next: bin/serve-all.sh $TARGET, then node shots.mjs shots.json <out> --login=maya:fernway-demo-maya"
