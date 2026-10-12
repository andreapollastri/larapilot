#!/usr/bin/env bash
# The agents of Fernway: Boost's guidelines and skills, and the skills of the larapilot package.
#   tools/docs-demo/bin/agents.sh <target-dir>
# Runs before seed.sh: Boost calls artisan in subprocesses that load no prepend file, and their
# "class not found" would otherwise land in the log the Logs page shows.
set -euo pipefail
KIT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO="$(cd "$KIT/../.." && pwd)"
TARGET="$(cd "${1:?usage: agents.sh <target-dir>}" && pwd -P)"
APP="$TARGET/fernway"
TB="$KIT/bin/tb"

# Boost: guidelines and the skills of the agents it finds on this machine (Cursor here) ...
"$TB" "$TARGET" boost:install --guidelines --skills -n >/dev/null
# ... and the skills Boost publishes from the larapilot package once the app installs it from Packagist.
# The repository's vendor/ cannot list the package itself, so they are copied the way boost:update does.
for agent in .claude .cursor; do
  mkdir -p "$APP/$agent/skills"
  cp -R "$REPO/resources/boost/skills/"larapilot-* "$APP/$agent/skills/"
  [ -e "$APP/$agent/skills/studio-release-notes" ] || cp -R "$APP/.ai/skills/studio-release-notes" "$APP/$agent/skills/"
done
cp "$APP/AGENTS.md" "$APP/CLAUDE.md"

