#!/usr/bin/env bash
# Creates an empty Fernway app from the testbench skeleton, outside the repository.
#
#   tools/docs-demo/bin/setup-app.sh <target-dir>
#
# <target-dir>/fernway  the Laravel app (base path), later a git repository
# <target-dir>/work     the testbench working path: testbench.yaml, prepend.php, vendor symlink
#
# Nothing is written into vendor/orchestra/testbench-core/laravel: the test suite of
# the package uses that folder and wipes it between tests.
set -euo pipefail

KIT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO="$(cd "$KIT/../.." && pwd)"
TARGET="${1:?usage: setup-app.sh <target-dir>}"
mkdir -p "$TARGET"
TARGET="$(cd "$TARGET" && pwd -P)"

case "$TARGET/" in
  "$REPO"/*) echo "The target must be outside the repository: $TARGET" >&2; exit 2 ;;
esac

APP="$TARGET/fernway"
WORK="$TARGET/work"
SKELETON="$REPO/vendor/orchestra/testbench-core/laravel"

if [ ! -d "$SKELETON" ]; then
  echo "Run composer install in $REPO first (no testbench skeleton)." >&2
  exit 2
fi

rm -rf "$APP" "$WORK"
mkdir -p "$APP" "$WORK"

# The skeleton's own entries only: no .git, .larapilot, .env, composer.json, or the
# change-set-*/code-history-* leftovers the test suite writes next to them.
for entry in .env.example .gitignore app artisan bootstrap config database lang public resources routes server.php storage; do
  [ -e "$SKELETON/$entry" ] && cp -R "$SKELETON/$entry" "$APP/"
done

# Trap 1: without this marker testbench treats the folder as a fresh skeleton and recurses until OOM.
touch "$APP/bootstrap/.testbench-default-skeleton"
# Trap 2: testbench runs the skeleton's migrations/ folder on its own: Fernway has its own.
rm -rf "$APP/migrations" "$APP/database/migrations"/* "$APP/tests"
# Trap 3: a copied compiled view is newer than the package's Blade source and would be served stale.
find "$APP/storage/framework/views" -type f ! -name .gitignore -delete
rm -f "$APP/bootstrap/cache/"*.php "$APP/routes/.gitignore"
mkdir -p "$APP/database/migrations" "$APP/storage/logs" "$APP/storage/app/private"

ln -s "$REPO/vendor" "$APP/vendor"
# testbench hashes vendor/autoload.php inside the working path too.
ln -s "$REPO/vendor" "$WORK/vendor"

cat > "$WORK/testbench.yaml" <<YAML
laravel: $APP
providers:
  - Larapilot\\LarapilotServiceProvider
  - Laravel\\Mcp\\Server\\McpServiceProvider
  - App\\Providers\\FernwayServiceProvider
  - FernwayDemo\\DemoServiceProvider
YAML

# Loaded with php -d auto_prepend_file=... before testbench: the App\ and FernwayDemo\
# autoloaders, the frozen clock of the story, and an output buffer (php -S writes
# unbuffered, so a dump() before the headers would break the response).
cat > "$WORK/prepend.php" <<PHP
<?php
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server') { return; }
define('FERNWAY_APP', '$APP');
define('FERNWAY_KIT', '$KIT');
require '$REPO/vendor/autoload.php';
spl_autoload_register(static function (string \$class): void {
    foreach (['App\\\\' => FERNWAY_APP.'/app/', 'Database\\\\Seeders\\\\' => FERNWAY_APP.'/database/seeders/', 'FernwayDemo\\\\' => FERNWAY_KIT.'/php/'] as \$prefix => \$dir) {
        if (str_starts_with(\$class, \$prefix)) {
            \$file = \$dir.str_replace('\\\\', '/', substr(\$class, strlen(\$prefix))).'.php';
            if (is_file(\$file)) { require \$file; }
            return;
        }
    }
});
if (! class_exists('App\\\\Providers\\\\FernwayServiceProvider')) {
    // Before the app code is committed the provider is an empty one.
    eval('namespace App\\\\Providers; class FernwayServiceProvider extends \\\\Illuminate\\\\Support\\\\ServiceProvider {}');
}
// The story's clock: DEMO_NOW for commands, X-Demo-Now on a request of the traffic script.
\$now = PHP_SAPI === 'cli-server' ? (\$_SERVER['HTTP_X_DEMO_NOW'] ?? '') : getenv('DEMO_NOW');
if (\$now !== false && \$now !== '') {
    \\Illuminate\\Support\\Carbon::setTestNow(\\Illuminate\\Support\\Carbon::parse(\$now));
    \\Carbon\\CarbonImmutable::setTestNow(\\Carbon\\CarbonImmutable::parse(\$now));
}
if (PHP_SAPI === 'cli-server') { ob_start(); }
PHP

cat > "$APP/.env" <<ENV
APP_NAME=Fernway
APP_ENV=local
APP_KEY=base64:$(printf 'fernway-docs-demo-key-32-bytes!!' | base64)
APP_DEBUG=true
APP_URL=http://fernway.test
APP_TIMEZONE=UTC

DB_CONNECTION=sqlite
DB_DATABASE=$APP/database/database.sqlite

CACHE_STORE=file
CACHE_PREFIX=fernway-cache-
SESSION_DRIVER=file
QUEUE_CONNECTION=database
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_FROM_ADDRESS=bookings@fernway.example
MAIL_FROM_NAME=Fernway
LOG_CHANNEL=single
AUTH_MODEL='App\\Models\\User'
REDIS_PREFIX=fernway-docs-demo-
ENV
touch "$APP/database/database.sqlite"

echo "app:  $APP"
echo "work: $WORK"
