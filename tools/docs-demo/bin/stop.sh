#!/usr/bin/env bash
# Stops the servers serve.sh / serve-all.sh started for <target-dir> (and only those).
set -euo pipefail
TARGET="$(cd "${1:?usage: stop.sh <target-dir>}" && pwd -P)"
for pid in "$TARGET"/serve-*.pid; do
  [ -e "$pid" ] || continue
  kill "$(cat "$pid")" 2>/dev/null || true
  rm -f "$pid"
done
