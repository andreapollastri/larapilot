#!/usr/bin/env bash
#
# prj-ai — VPS provisioning for Ubuntu 24.04 LTS / 26.04 LTS
#
# Prepares a bare VPS to host multiple Laravel projects driven by Claude Code
# and Larapilot, accessed by several developers over SSH:
#   - PHP 8.3 / 8.4 / 8.5 (FPM, one pool per project) with tuned OPcache
#   - MySQL, Redis, Nginx (+ certbot), Supervisor, cron
#   - Node.js LTS, Composer, Claude Code
#   - Git provider CLIs: gh (GitHub), glab (GitLab), az (Azure DevOps)
#   - Admin CLI `prj-ai` + developer menu `prj-work` + universal `prj-pr`
#     (opens a PR/MR on GitHub, GitLab, Bitbucket or Azure DevOps) + `prj-token`
#     (each developer saves their own git token so commits/PRs are theirs)
#   - Atomic zero-downtime deploys: each release is built in its own directory
#     and the live 'current' symlink is flipped only after every build step
#     passes; `prj-ai rollback` flips back instantly
#   - Persistent tmux sessions that survive disconnects
#
# One file to copy to the server. It embeds and writes every other component.
# Re-running it is safe (idempotent) and is also how you upgrade the CLIs.
#
# Usage: sudo bash provision.sh
#
set -euo pipefail

[ "$(id -u)" = 0 ] || { echo "Run as root: sudo bash provision.sh" >&2; exit 1; }

# Supported Ubuntu releases (deliberate override: PRJ_FORCE=1).
. /etc/os-release
case "${ID:-}:${VERSION_ID:-}" in
    ubuntu:24.04|ubuntu:26.04) ;;
    *)
        if [ "${PRJ_FORCE:-0}" != 1 ]; then
            echo "Unsupported system: ${PRETTY_NAME:-unknown}" >&2
            echo "Supported: Ubuntu 24.04 LTS and 26.04 LTS (force with PRJ_FORCE=1)" >&2
            exit 1
        fi
        echo "WARNING: ${PRETTY_NAME:-?} is untested, continuing because PRJ_FORCE=1" >&2
        ;;
esac

export DEBIAN_FRONTEND=noninteractive
# needrestart prompts interactively during apt upgrades on 24.04: force
# automatic mode for the whole provisioning run.
export NEEDRESTART_MODE=a
export NEEDRESTART_SUSPEND=1
APT_OPTS=(-y -o Dpkg::Options::=--force-confold -o Dpkg::Options::=--force-confdef)

PHP_VERSIONS=(8.3 8.4 8.5)
PHP_EXTS=(cli fpm mysql mbstring xml curl zip gd intl bcmath readline opcache sqlite3 redis)
NODE_MAJOR=22

echo "==> Base packages"
apt-get update -y
apt-get upgrade "${APT_OPTS[@]}"
apt-get install "${APT_OPTS[@]}" curl wget git unzip zip tmux jq acl ufw fail2ban cron \
    software-properties-common ca-certificates gnupg lsb-release unattended-upgrades

echo "==> PHP ${PHP_VERSIONS[*]} (ondrej/php PPA)"
add-apt-repository -y ppa:ondrej/php
apt-get update -y
for v in "${PHP_VERSIONS[@]}"; do
    pkgs=()
    for e in "${PHP_EXTS[@]}"; do pkgs+=("php${v}-${e}"); done
    apt-get install "${APT_OPTS[@]}" "${pkgs[@]}"
    # Shared OPcache / realpath tuning. Deploys are ATOMIC: every deploy builds a
    # fresh release directory and nginx passes $realpath_root, so OPcache keys on
    # a brand-new path each time and picks up new code with no FPM reload and no
    # dropped request. validate_timestamps stays on so in-place edits inside a
    # developer's personal workspace are still seen without a reload.
    cat > "/etc/php/${v}/fpm/conf.d/99-prj-ai.ini" <<'EOF'
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=192
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=15
realpath_cache_size=4096K
realpath_cache_ttl=600
EOF
    systemctl enable --now "php${v}-fpm"
    systemctl reload "php${v}-fpm" || true
done

echo "==> Composer"
if ! command -v composer >/dev/null 2>&1; then
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php8.4 /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi

echo "==> MySQL + Redis"
apt-get install "${APT_OPTS[@]}" mysql-server redis-server
systemctl enable --now mysql redis-server

echo "==> Nginx + certbot"
apt-get install "${APT_OPTS[@]}" nginx python3-certbot-nginx
systemctl enable --now nginx

echo "==> Supervisor"
apt-get install "${APT_OPTS[@]}" supervisor
systemctl enable --now supervisor cron

echo "==> Node.js LTS (${NODE_MAJOR}.x)"
if ! command -v node >/dev/null 2>&1; then
    curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" | bash -
    apt-get install "${APT_OPTS[@]}" nodejs
fi

echo "==> Claude Code (global binary; each user logs in with their own plan)"
npm install -g @anthropic-ai/claude-code

# Every supported provider's CLI is installed so the active provider can be
# switched later with 'prj-ai config' without re-provisioning. Per-user login
# (gh auth login / glab auth login / az devops login) is done in each home.
echo "==> GitHub CLI (gh)"
if ! command -v gh >/dev/null 2>&1; then
    mkdir -p -m 755 /etc/apt/keyrings
    curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg \
        -o /etc/apt/keyrings/githubcli-archive-keyring.gpg
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" \
        > /etc/apt/sources.list.d/github-cli.list
    apt-get update -y && apt-get install "${APT_OPTS[@]}" --no-install-recommends gh
fi

echo "==> GitLab CLI (glab)"
if ! command -v glab >/dev/null 2>&1; then
    glab_arch=$(dpkg --print-architecture)
    case "$glab_arch" in amd64|arm64) ;; *) glab_arch="" ;; esac
    glab_ver=$(curl -fsSL "https://gitlab.com/api/v4/projects/gitlab-org%2Fcli/releases" 2>/dev/null \
        | jq -r '.[0].tag_name // empty' | sed 's/^v//')
    if [ -n "$glab_arch" ] && [ -n "$glab_ver" ] \
        && curl -fsSL "https://gitlab.com/gitlab-org/cli/-/releases/v${glab_ver}/downloads/glab_${glab_ver}_linux_${glab_arch}.tar.gz" -o /tmp/glab.tgz; then
        mkdir -p /tmp/glab && tar -xzf /tmp/glab.tgz -C /tmp/glab \
            && install -m 0755 "$(find /tmp/glab -type f -name glab | head -n1)" /usr/local/bin/glab
        rm -rf /tmp/glab /tmp/glab.tgz
    else
        echo "WARNING: glab install skipped (offline or unsupported arch) — install it later if you use GitLab." >&2
    fi
fi

echo "==> Azure CLI (az — for Azure DevOps: az repos, az devops)"
if ! command -v az >/dev/null 2>&1; then
    curl -sL https://aka.ms/InstallAzureCLIDeb | bash
fi
# Bitbucket Cloud has no first-class CLI: prj-pr talks to its REST API with
# curl + jq (both already installed).

echo "==> Developer group and directories"
groupadd -f prjdev
mkdir -p /etc/prj-ai/projects /etc/prj-ai/previews /srv/prj
chmod 755 /etc/prj-ai /etc/prj-ai/projects /etc/prj-ai/previews /srv/prj

echo "==> Persistent sessions (tmux survives logout)"
mkdir -p /etc/systemd/logind.conf.d
cat > /etc/systemd/logind.conf.d/prj-ai.conf <<'EOF'
[Login]
KillUserProcesses=no
EOF
systemctl restart systemd-logind || true

cat > /etc/tmux.conf <<'EOF'
set -g mouse on
set -g history-limit 100000
set -g default-terminal "screen-256color"
set -s escape-time 0
set -g status-style "bg=colour24,fg=white"
set -g status-left " #S "
set -g status-right " %H:%M %d/%m "
EOF

echo "==> Generating and installing the CLIs (embedded in this script)"

cat > /usr/local/sbin/prj-ai <<'EMBED_PRJ_AI'
#!/usr/bin/env bash
#
# prj-ai — manage multi-PHP Laravel projects on a shared VPS
#
# Root commands:
#   prj-ai config                 configure base domain, git provider, service token, defaults
#   prj-ai list                   list projects
#   prj-ai add                    add a project (interactive)
#   prj-ai del <project>          delete a project
#   prj-ai php <project> [ver]    change PHP version
#   prj-ai user-add               create a developer user
#   prj-ai user-del <user>        delete a developer user
#   prj-ai deploy <project>       build a new release and flip to it (zero-downtime)
#   prj-ai rollback <project>     flip back to the previous release
#   prj-ai preview <sub> ...      manage per-developer preview URLs
#                                 (list | up <user> <project> | down <user> <project> [--drop-db] | reap)
#
# Developer command (via NOPASSWD sudo):
#   prj-ai workspace-init <proj>  create/refresh the caller's personal workspace
#                                 (and, if the project opted in, their preview)
#
set -euo pipefail

CONF_DIR=/etc/prj-ai
CONF="$CONF_DIR/prj-ai.conf"
CRED="$CONF_DIR/git-credentials"
REG="$CONF_DIR/projects"
PREV="$CONF_DIR/previews"
PREV_HTPASSWD="$CONF_DIR/preview.htpasswd"
SRV=/srv/prj
DEV_GROUP=prjdev
PHP_VERSIONS=("8.3" "8.4" "8.5")

# ---------------------------------------------------------------- helpers ---

die()  { echo "prj-ai: $*" >&2; exit 1; }
warn() { echo "prj-ai: $*" >&2; }

need_root() { [ "$(id -u)" = 0 ] || die "this command needs root (sudo prj-ai ...)"; }

load_conf() { [ -f "$CONF" ] || die "not configured: run 'sudo prj-ai config' first"; . "$CONF"; }

valid_name() { [[ "$1" =~ ^[a-z0-9][a-z0-9-]{0,26}$ ]]; }
valid_user() { [[ "$1" =~ ^[a-z][a-z0-9._-]{0,30}$ ]]; }

valid_php() {
    local v
    for v in "${PHP_VERSIONS[@]}"; do [ "$1" = "$v" ] && command -v "php$1" >/dev/null && return 0; done
    return 1
}

proj_file() { echo "$REG/$1.env"; }

load_proj() {
    valid_name "$1" || die "invalid project name: $1"
    [ -f "$(proj_file "$1")" ] || die "project '$1' does not exist (see: prj-ai list)"
    . "$(proj_file "$1")"
}

ask() { # ask "Question" default -> prints the answer
    local q=$1 def=${2:-} ans
    if [ -n "$def" ]; then read -rp "$q [$def]: " ans; echo "${ans:-$def}";
    else read -rp "$q: " ans; echo "$ans"; fi
}

set_env_kv() { # file KEY value — replace or append
    local f=$1 k=$2 v=$3
    if grep -qE "^${k}=" "$f" 2>/dev/null; then
        sed -i "s|^${k}=.*|${k}=${v}|" "$f"
    else
        printf '%s=%s\n' "$k" "$v" >> "$f"
    fi
}

as_proj() { # NAME command... — run in the canonical repo as the project system user
    local name=$1; shift
    sudo -u "prj-$name" -H bash -c "cd '$SRV/$name/repo' && $*"
}

# Normalise the provider: 'devops' is kept as an alias for 'azure'.
norm_provider() {
    case "${1:-}" in
        devops|azure)     echo azure ;;
        github|gh)        echo github ;;
        gitlab|glab)      echo gitlab ;;
        bitbucket|bb)     echo bitbucket ;;
        *)                echo "" ;;
    esac
}

provider_host_default() {
    case "$1" in
        github)    echo github.com ;;
        gitlab)    echo gitlab.com ;;
        bitbucket) echo bitbucket.org ;;
        azure)     echo dev.azure.com ;;
    esac
}

# Username stored in the service credential line "https://USER:TOKEN@host".
provider_user_default() {
    case "$1" in
        github)    echo x-access-token ;;
        gitlab)    echo oauth2 ;;
        bitbucket) echo "" ;;      # must be the real Bitbucket workspace user
        azure)     echo pat ;;
    esac
}

provider_token_hint() {
    case "$1" in
        github)    echo "GitHub PAT (classic: scope 'repo'; or fine-grained: Contents Read & Write)" ;;
        gitlab)    echo "GitLab PAT with scopes read_repository, write_repository" ;;
        bitbucket) echo "Bitbucket App Password: Repositories Read/Write + Pull requests Read/Write" ;;
        azure)     echo "Azure DevOps PAT with scope Code (Read & Write)" ;;
    esac
}

repo_url_example() {
    case "$1" in
        github)    echo "https://github.com/org/repo.git" ;;
        gitlab)    echo "https://gitlab.com/group/repo.git" ;;
        bitbucket) echo "https://bitbucket.org/workspace/repo.git" ;;
        azure)     echo "https://dev.azure.com/ORG/Project/_git/repo" ;;
    esac
}

# ---------------------------------------------------- config writers -------

write_fpm_pool() { # NAME PHPV
    local name=$1 phpv=$2
    cat > "/etc/php/$phpv/fpm/pool.d/prj-$name.conf" <<EOF
[prj-$name]
user = prj-$name
group = prj-$name
listen = /run/php/prj-$name.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500
pm.process_idle_timeout = 20s
php_admin_value[memory_limit] = 256M
php_admin_value[error_log] = $SRV/$name/logs/php.log
php_admin_flag[log_errors] = on
EOF
}

write_nginx() { # NAME FQDN
    local name=$1 fqdn=$2
    cat > "/etc/nginx/sites-available/prj-$name" <<EOF
server {
    listen 80;
    server_name $fqdn;
    # 'current' is an atomically-swapped symlink to the live release.
    root $SRV/$name/current/public;
    index index.php;
    client_max_body_size 64m;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ ^/index\.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/prj-$name.sock;
        # Resolve the 'current' symlink here so PHP-FPM sees the real release
        # path: OPcache then keys on a fresh path per deploy — new code is live
        # the instant the symlink flips, with no reload.
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        fastcgi_read_timeout 120;
    }

    location ~* \.(?:css|js|mjs|map|woff2?|ttf|otf|eot|svg|png|jpe?g|gif|webp|avif|ico)\$ {
        expires 7d;
        access_log off;
        add_header Cache-Control "public, max-age=604800";
        try_files \$uri /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
EOF
    ln -sf "/etc/nginx/sites-available/prj-$name" "/etc/nginx/sites-enabled/prj-$name"
}

write_cron() { # NAME PHPV
    local name=$1 phpv=$2
    cat > "/etc/cron.d/prj-$name" <<EOF
* * * * * prj-$name cd $SRV/$name/current && /usr/bin/php$phpv artisan schedule:run >> $SRV/$name/logs/schedule.log 2>&1
EOF
    chmod 644 "/etc/cron.d/prj-$name"
}

write_supervisor() { # NAME PHPV
    local name=$1 phpv=$2
    cat > "/etc/supervisor/conf.d/prj-$name.conf" <<EOF
[program:prj-$name-queue]
command=/usr/bin/php$phpv $SRV/$name/current/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=prj-$name
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=$SRV/$name/logs/queue.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
EOF
}

# ------------------------------------------------- per-developer previews --
# Each (developer, project) pair the developer has opened gets its own URL
#   <user>-<project>.<BASE_DOMAIN>
# serving that developer's live working tree (~/work/<project>) with its own
# MySQL database (seeded once from the project's staging DB). FPM runs as the
# developer's own uid; every preview vhost is behind shared HTTP Basic Auth.

prev_id()   { echo "${1}-${2}"; }                    # USER PROJECT
prev_db()   { echo "prv_${2//-/_}_${1//-/_}"; }      # USER PROJECT -> mysql name
prev_file() { echo "$PREV/$(prev_id "$1" "$2").env"; }

write_preview_pool() { # USER PROJECT PHPV
    local u=$1 name=$2 phpv=$3 id ws
    id=$(prev_id "$u" "$name"); ws="/home/$u/work/$name"
    cat > "/etc/php/$phpv/fpm/pool.d/prev-$id.conf" <<EOF
[prev-$id]
user = $u
group = $u
listen = /run/php/prev-$id.sock
listen.owner = www-data
listen.group = www-data
; Preview traffic is light and bursty — spawn on demand, die when idle so an
; unused preview costs no memory.
pm = ondemand
pm.max_children = 4
pm.process_idle_timeout = 30s
pm.max_requests = 300
php_admin_value[memory_limit] = 256M
php_admin_value[error_log] = $ws/storage/logs/fpm.log
php_admin_flag[log_errors] = on
EOF
}

write_preview_nginx() { # USER PROJECT
    local u=$1 name=$2 id fqdn ws
    id=$(prev_id "$u" "$name"); fqdn="$id.$BASE_DOMAIN"; ws="/home/$u/work/$name"
    cat > "/etc/nginx/sites-available/prev-$id" <<EOF
server {
    listen 80;
    server_name $fqdn;
    root $ws/public;
    index index.php;
    client_max_body_size 64m;
    access_log /var/log/nginx/prev-$id.log;

    # A preview serves uncommitted, often APP_DEBUG=true code — always gated.
    auth_basic "Preview — $name";
    auth_basic_user_file $PREV_HTPASSWD;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ ^/index\.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/prev-$id.sock;
        fastcgi_read_timeout 120;
    }

    location ~* \.(?:css|js|mjs|map|woff2?|ttf|otf|eot|svg|png|jpe?g|gif|webp|avif|ico)\$ {
        expires 1h;
        access_log off;
        try_files \$uri /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    location ~ /\.(?!well-known) { deny all; }
}
EOF
    ln -sf "/etc/nginx/sites-available/prev-$id" "/etc/nginx/sites-enabled/prev-$id"
}

# Provision (or cheaply refresh) the preview for one developer + project. Assumes
# the workspace clone already exists. Heavy one-time steps (DB seed, .env rewrite,
# asset build, cert) run only the first time.
preview_up() { # USER PROJECT PHPV
    local u=$1 name=$2 phpv=$3 id fqdn ws pf devdb devpass fresh
    id=$(prev_id "$u" "$name"); fqdn="$id.$BASE_DOMAIN"
    ws="/home/$u/work/$name"; pf=$(prev_file "$u" "$name")
    [ -d "$ws/.git" ] || { warn "no workspace at $ws — preview skipped"; return 0; }
    mkdir -p "$PREV"

    fresh=1
    [ -f "$pf" ] && [ -f "/etc/nginx/sites-enabled/prev-$id" ] && fresh=0

    if [ "$fresh" = 1 ]; then
        devdb=$(prev_db "$u" "$name"); devpass=$(openssl rand -hex 16)
        echo "==> Preview DB $devdb (seeded from ${DB})"
        mysql <<SQL || { warn "preview DB setup failed for $devdb — preview skipped"; return 0; }
CREATE DATABASE IF NOT EXISTS \`$devdb\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$devdb'@'localhost' IDENTIFIED BY '$devpass';
GRANT ALL PRIVILEGES ON \`$devdb\`.* TO '$devdb'@'localhost';
FLUSH PRIVILEGES;
SQL
        mysqldump --single-transaction --no-tablespaces --routines --triggers "$DB" 2>/dev/null \
            | mysql "$devdb" 2>/dev/null \
            || warn "could not seed $devdb from $DB — the developer can run 'php artisan migrate --seed'"

        # Point the developer's own .env at their preview DB + URL, once.
        if [ -f "$ws/.env" ] && ! grep -qxF '# prj-ai preview' "$ws/.env"; then
            sudo -u "$u" -H bash -c "printf '\n# prj-ai preview\n' >> '$ws/.env'" || true
            set_env_kv "$ws/.env" APP_ENV local
            set_env_kv "$ws/.env" APP_URL "https://$fqdn"
            set_env_kv "$ws/.env" DB_DATABASE "$devdb"
            set_env_kv "$ws/.env" DB_USERNAME "$devdb"
            set_env_kv "$ws/.env" DB_PASSWORD "$devpass"
            set_env_kv "$ws/.env" CACHE_PREFIX "prev_${id}_"
            set_env_kv "$ws/.env" REDIS_PREFIX "prev_${id}_"
            set_env_kv "$ws/.env" SESSION_COOKIE "prev_${id}_session"
            chown "$u:$u" "$ws/.env"; chmod 600 "$ws/.env"
        fi

        # Build front-end assets so the first page render works.
        if [ -f "$ws/package.json" ] && command -v npm >/dev/null 2>&1; then
            sudo -u "$u" -H bash -c "cd '$ws' && { npm ci --no-audit --no-fund --silent || npm install --no-audit --no-fund --silent; } && \
                node -e \"process.exit((require('./package.json').scripts||{}).build?0:1)\" && npm run build --silent" \
                >/dev/null 2>&1 || warn "preview asset build failed — run 'npm run build' in $ws"
        fi

        cat > "$pf" <<EOF
ID="$id"
DEV="$u"
PROJECT="$name"
FQDN="$fqdn"
DB="$devdb"
PHP="$phpv"
CREATED="$(date -Iseconds)"
EOF
        chmod 644 "$pf"
    fi

    # The web server (www-data) must traverse the 0700 home to reach the public/
    # dir. 0711 lets it pass without listing; ~/.ssh, ~/.git-credentials and
    # ~/.claude keep their own restrictive modes.
    chmod 711 "/home/$u"
    chmod o+x "$ws" 2>/dev/null || true
    [ -d "$ws/public" ] && chmod o+rx "$ws/public" 2>/dev/null || true

    write_preview_pool "$u" "$name" "$phpv"; systemctl reload "php$phpv-fpm" 2>/dev/null || true
    write_preview_nginx "$u" "$name"
    nginx -t >/dev/null 2>&1 && systemctl reload nginx || warn "nginx config test failed after adding preview $id"

    if [ "$fresh" = 1 ] && [ "${LETSENCRYPT:-no}" = yes ] && [ -n "${LETSENCRYPT_EMAIL:-}" ]; then
        certbot --nginx -d "$fqdn" --non-interactive --agree-tos -m "$LETSENCRYPT_EMAIL" >/dev/null 2>&1 \
            || warn "certbot failed for $fqdn (is *.$BASE_DOMAIN pointed at this host?)"
    fi
    echo "Preview: https://$fqdn"
}

preview_down() { # USER PROJECT [--drop-db]
    local u=$1 name=$2 drop=${3:-} id pf v ddb
    id=$(prev_id "$u" "$name"); pf=$(prev_file "$u" "$name")
    rm -f "/etc/nginx/sites-enabled/prev-$id" "/etc/nginx/sites-available/prev-$id"
    nginx -t >/dev/null 2>&1 && systemctl reload nginx || true
    for v in "${PHP_VERSIONS[@]}"; do
        [ -f "/etc/php/$v/fpm/pool.d/prev-$id.conf" ] || continue
        rm -f "/etc/php/$v/fpm/pool.d/prev-$id.conf"
        systemctl reload "php$v-fpm" 2>/dev/null || true
    done
    if [ "$drop" = "--drop-db" ] && [ -f "$pf" ]; then
        ddb=$(. "$pf"; echo "${DB:-}")
        [ -n "$ddb" ] && mysql -e "DROP DATABASE IF EXISTS \`$ddb\`; DROP USER IF EXISTS '$ddb'@'localhost';" 2>/dev/null || true
    fi
    rm -f "$pf" "/var/log/nginx/prev-$id.log"
    echo "preview '$id' removed${drop:+ (database dropped)}"
}

preview_reap() { # tear down previews idle > PREVIEW_TTL_DAYS or whose workspace/user is gone
    [ -f "$CONF" ] && . "$CONF" || true
    [ -d "$PREV" ] || return 0
    local ttl=${PREVIEW_TTL_DAYS:-14} f ID DEV PROJECT FQDN DB PHP CREATED ws log
    for f in "$PREV"/*.env; do
        [ -e "$f" ] || break
        ID=""; DEV=""; PROJECT=""
        # shellcheck disable=SC1090
        . "$f"
        [ -n "$DEV" ] && [ -n "$PROJECT" ] || { rm -f "$f"; continue; }
        ws="/home/$DEV/work/$PROJECT"; log="/var/log/nginx/prev-$ID.log"
        if [ ! -d "$ws/.git" ] || ! id "$DEV" >/dev/null 2>&1; then
            # Workspace or user gone (project/user deleted) — nothing to keep.
            echo "prj-ai: reaping orphaned preview $ID (+ database)"
            preview_down "$DEV" "$PROJECT" --drop-db
            continue
        fi
        if [ -f "$log" ] && find "$log" -mtime -"$ttl" 2>/dev/null | grep -q .; then
            continue   # seen within the TTL — keep serving
        fi
        # Idle: stop serving but keep the DB — the developer may come back.
        echo "prj-ai: reaping idle preview $ID (database kept)"
        preview_down "$DEV" "$PROJECT"
    done
}

fix_perms() { # NAME PATH — own by prj-NAME, group-readable by prjdev, setgid dirs
    local name=$1 p=$2
    chown -R "prj-$name:$DEV_GROUP" "$p"
    chmod -R g+rX "$p"
    find "$p" -type d -exec chmod g+s {} + 2>/dev/null || true
}

fix_repo_perms() { # NAME — normalise the whole project tree
    local name=$1 base="$SRV/$name"
    chown -R "prj-$name:$DEV_GROUP" "$base"
    chown -h "prj-$name:$DEV_GROUP" "$base/current" 2>/dev/null || true
    # 751: the web server (www-data, not in prjdev) must TRAVERSE $base to reach
    # current/public, but must not be able to list it. Release trees keep their
    # default o+rX so nginx can still serve static assets.
    chmod 751 "$base"
    chmod -R g+rX "$base/repo" "$base/releases" "$base/shared" 2>/dev/null || true
    find "$base/repo" "$base/releases" -type d -exec chmod g+s {} + 2>/dev/null || true
    # shared/ stays world-traversable so nginx can serve public uploads via the
    # storage symlink; the .env itself is locked to owner+group.
    [ -f "$base/shared/.env" ] && chmod 640 "$base/shared/.env" || true
}

as_rel() { # NAME DIR command... — run as the project user inside a release dir
    local name=$1 dir=$2; shift 2
    sudo -u "prj-$name" -H bash -c "cd '$dir' && $*"
}

dlog() { # NAME message... — append one line to the project deploy log
    local name=$1; shift
    printf '%s  %s\n' "$(date -Iseconds)" "$*" >> "$SRV/$name/logs/deploy.log" 2>/dev/null || true
}

current_release() { # NAME -> absolute path of the live release, or empty
    local l="$SRV/$1/current"
    [ -L "$l" ] && readlink -f "$l" || true
}

# List release dirs newest-first, one absolute path per line, excluding 'current'.
releases_but_current() { # NAME
    local name=$1 cur; cur=$(current_release "$name")
    ls -1dt "$SRV/$name/releases"/*/ 2>/dev/null | sed 's:/*$::' \
        | { if [ -n "$cur" ]; then grep -vxF "$cur" || true; else cat; fi; }
}

prev_release() { # NAME -> newest release that is not 'current', or empty
    releases_but_current "$1" | head -n1
}

prune_releases() { # NAME — keep the last RELEASES_KEEP releases for fast rollback
    local name=$1 keep=${RELEASES_KEEP:-3} cur d
    cur=$(current_release "$name")
    # node_modules is a build-time dependency only — drop it everywhere but 'current'.
    for d in "$SRV/$name/releases"/*/; do
        d=${d%/}
        [ -d "$d" ] || continue
        [ "$d" = "$cur" ] && continue
        rm -rf "$d/node_modules"
    done
    releases_but_current "$name" | tail -n +"$keep" \
        | while read -r d; do [ -n "$d" ] && [ -d "$d" ] && rm -rf "$d" || true; done
}

# Build a fresh release from repo/ HEAD and, only if every step succeeds, flip
# the 'current' symlink to it atomically. On ANY failure the half-built release
# is removed and 'current' is left untouched — a broken build never goes live.
# Sets REL_TAG to the new release id on success.
#
#   publish_release NAME PHPV MODE [CHANGED-FILE-LIST]
#     MODE=first   initial build during 'prj-ai add' (always full build + migrate)
#     MODE=deploy  incremental — composer / assets / migrate run only when the
#                  matching files changed between the old and new commit
REL_TAG=""
publish_release() {
    local name=$1 phpv=$2 mode=$3 changed=${4:-}
    local base="$SRV/$name" repo="$SRV/$name/repo"
    local sha ts rel prev blog
    sha=$(git -C "$repo" rev-parse HEAD)
    ts=$(date -u +%Y%m%d%H%M%S)
    rel="$base/releases/$ts-${sha:0:8}"
    prev=$(current_release "$name")
    blog="$base/logs/deploy.log"

    _abort() { warn "$1 — release $ts aborted, still serving ${prev:+$(basename "$prev")}"; rm -rf "$rel"; }

    mkdir -p "$base/releases" "$rel"
    # Export exactly the tracked tree at HEAD — no .git, no untracked cruft.
    if ! git -C "$repo" archive --format=tar HEAD | tar -x -C "$rel"; then
        _abort "git archive failed"; return 1
    fi
    printf 'sha=%s\ndeployed_at=%s\n' "$sha" "$(date -Iseconds)" > "$rel/RELEASE"

    # Per-viewer state lives in shared/ and is symlinked into every release.
    rm -rf "$rel/storage"
    ln -sfn "../../shared/storage" "$rel/storage"
    ln -sfn "../../shared/.env" "$rel/.env"
    mkdir -p "$rel/bootstrap/cache"

    # Carry built dependencies forward so the common code-only deploy is fast.
    if [ -n "$prev" ] && [ -d "$prev/vendor" ]; then
        cp -a "$prev/vendor" "$rel/vendor"
        # These are rewritten in place by composer — never share them with prev.
        rm -rf "$rel/vendor/composer" "$rel/vendor/autoload.php"
    fi
    [ -n "$prev" ] && [ -d "$prev/node_modules" ] && cp -a "$prev/node_modules" "$rel/node_modules" || true
    [ -n "$prev" ] && [ -d "$prev/public/build" ] && cp -a "$prev/public/build" "$rel/public/build" || true

    fix_perms "$name" "$rel"

    local want_composer=1 want_assets=0 want_migrate=0
    if [ "$mode" = deploy ]; then
        echo "$changed" | grep -qE '^composer\.(json|lock)$' || want_composer=0
        echo "$changed" | grep -qE '^(package\.json|package-lock\.json|pnpm-lock\.yaml|yarn\.lock|bun\.lockb|vite\.config\.[a-z]+|resources/(js|css|sass|scss|ts)/)' && want_assets=1
        echo "$changed" | grep -qE '^database/migrations/' && want_migrate=1
        [ -d "$rel/vendor" ] || want_composer=1
    else
        want_assets=1; want_migrate=1
    fi

    if [ "$want_composer" = 1 ]; then
        as_rel "$name" "$rel" "COMPOSER_NO_AUDIT=1 php$phpv /usr/local/bin/composer install \
            --no-interaction --no-dev --prefer-dist --no-progress --optimize-autoloader" >> "$blog" 2>&1 \
            || { _abort "composer install failed"; return 1; }
    else
        as_rel "$name" "$rel" "php$phpv /usr/local/bin/composer dump-autoload --no-dev --optimize --no-interaction" >> "$blog" 2>&1 \
            || { _abort "composer dump-autoload failed"; return 1; }
    fi

    [ "$mode" = first ] && { as_rel "$name" "$rel" "php$phpv artisan key:generate --force" >> "$blog" 2>&1 || true; }
    as_rel "$name" "$rel" "php$phpv artisan storage:link --force" >> "$blog" 2>&1 || true

    if [ "$want_assets" = 1 ] && [ -f "$rel/package.json" ]; then
        if command -v npm >/dev/null 2>&1; then
            as_rel "$name" "$rel" "npm ci --no-audit --no-fund --silent || npm install --no-audit --no-fund --silent" >> "$blog" 2>&1 \
                || { _abort "npm install failed"; return 1; }
            if as_rel "$name" "$rel" "node -e \"process.exit((require('./package.json').scripts||{}).build?0:1)\"" 2>/dev/null; then
                as_rel "$name" "$rel" "npm run build --silent" >> "$blog" 2>&1 \
                    || { _abort "npm run build failed"; return 1; }
            fi
        else
            warn "npm missing — skipping asset build for '$name'"
        fi
    fi

    as_rel "$name" "$rel" "php$phpv artisan optimize" >> "$blog" 2>&1 \
        || { as_rel "$name" "$rel" "php$phpv artisan optimize:clear" >> "$blog" 2>&1 || true; }

    # Migrations run against the shared DB just before the flip. Optionally hold
    # the site in maintenance mode across the migrate + flip window.
    local down=no
    if [ "$want_migrate" = 1 ]; then
        if [ "${MIGRATE_MAINTENANCE:-no}" = yes ] && [ -n "$prev" ]; then
            as_rel "$name" "$prev" "php$phpv artisan down --render='errors::503' --retry=15" >> "$blog" 2>&1 && down=yes || true
        fi
        if ! as_rel "$name" "$rel" "php$phpv artisan migrate --force" >> "$blog" 2>&1; then
            [ "$down" = yes ] && { as_rel "$name" "$prev" "php$phpv artisan up" >> "$blog" 2>&1 || true; }
            _abort "migrate failed (schema may be partially applied — check $blog)"; return 1
        fi
    fi

    fix_perms "$name" "$rel"

    # Atomic flip: rename() over the old symlink — there is never a moment with
    # no target, so no request can 404 during the swap.
    ln -sfn "$rel" "$base/current.new"
    if ! mv -T "$base/current.new" "$base/current"; then
        rm -f "$base/current.new"
        [ "$down" = yes ] && { as_rel "$name" "$rel" "php$phpv artisan up" >> "$blog" 2>&1 || true; }
        _abort "could not flip 'current' symlink"; return 1
    fi
    chown -h "prj-$name:$DEV_GROUP" "$base/current" 2>/dev/null || true
    [ "$down" = yes ] && { as_rel "$name" "$rel" "php$phpv artisan up" >> "$blog" 2>&1 || true; }

    prune_releases "$name"
    REL_TAG="$ts-${sha:0:8}"
}

# --------------------------------------------------------------- commands --

cmd_config() {
    need_root
    mkdir -p "$CONF_DIR" "$REG"
    [ -f "$CONF" ] && . "$CONF"

    echo "== prj-ai configuration =="
    local base_domain default_php default_branch le_enable le_email
    base_domain=$(ask "Base domain (e.g. company.com)" "${BASE_DOMAIN:-}")
    [ -n "$base_domain" ] || die "base domain is required"
    default_php=$(ask "Default PHP version" "${DEFAULT_PHP:-8.4}")
    valid_php "$default_php" || die "invalid / not installed PHP version: $default_php"
    default_branch=$(ask "Default deploy branch" "${DEFAULT_BRANCH:-develop}")
    le_enable=$(ask "Enable Let's Encrypt for new projects? (yes/no)" "${LETSENCRYPT:-yes}")
    le_email=$(ask "Let's Encrypt email" "${LETSENCRYPT_EMAIL:-}")

    echo
    echo "-- Per-developer previews (<user>-<project>.$base_domain) --"
    echo "   Needs a wildcard DNS record *.$base_domain -> this host."
    local prev_ttl prev_user prev_pass
    prev_ttl=$(ask "Idle days before an unused preview is torn down" "${PREVIEW_TTL_DAYS:-14}")
    prev_user=$(ask "Preview Basic Auth username (shared by all preview URLs)" "${PREVIEW_USER:-team}")
    read -rsp "Preview Basic Auth password (enter to keep current): " prev_pass; echo

    echo
    echo "-- Git provider --"
    local raw provider host user
    raw=$(ask "Git provider (github/gitlab/bitbucket/azure)" "${GIT_PROVIDER:-github}")
    provider=$(norm_provider "$raw")
    [ -n "$provider" ] || die "invalid provider: use github, gitlab, bitbucket or azure"
    echo "   Service token: $(provider_token_hint "$provider")"
    echo "   Create it from a dedicated service / bot account; it expires — rerun 'prj-ai config' to rotate."

    echo
    echo "-- Git credentials for the canonical checkout (used ONLY by root) --"
    host=$(ask "Git host" "${GIT_HOST:-$(provider_host_default "$provider")}")
    local user_def; user_def=$(provider_user_default "$provider")
    if [ "$provider" = bitbucket ] && [ -z "$user_def" ]; then
        user=$(ask "Bitbucket username for the App Password" "${GIT_USER:-}")
        [ -n "$user" ] || die "Bitbucket requires a real username for the App Password"
    else
        user=$(ask "Username for the token" "${GIT_USER:-$user_def}")
    fi
    read -rsp "Git token / PAT / App Password (enter to keep current): " git_token; echo

    cat > "$CONF" <<EOF
BASE_DOMAIN="$base_domain"
DEFAULT_PHP="$default_php"
DEFAULT_BRANCH="$default_branch"
LETSENCRYPT="$le_enable"
LETSENCRYPT_EMAIL="$le_email"
GIT_PROVIDER="$provider"
GIT_HOST="$host"
GIT_USER="$user"
PREVIEW_USER="$prev_user"
PREVIEW_TTL_DAYS="$prev_ttl"
EOF
    chmod 600 "$CONF"

    if [ -n "$git_token" ]; then
        printf 'https://%s:%s@%s\n' "$user" "$git_token" "$host" > "$CRED"
        chmod 600 "$CRED"
        echo "Token stored in $CRED (root only)."
    fi

    if [ -n "$prev_pass" ]; then
        # apr1 (Apache MD5) hash via openssl — no apache2-utils dependency.
        printf '%s:%s\n' "$prev_user" "$(openssl passwd -apr1 "$prev_pass")" > "$PREV_HTPASSWD"
        chmod 640 "$PREV_HTPASSWD"; chown root:www-data "$PREV_HTPASSWD"
        echo "Preview Basic Auth set for '$prev_user'."
    elif [ ! -f "$PREV_HTPASSWD" ]; then
        echo "NOTE: no preview password set — previews stay OFF until you rerun 'prj-ai config'."
    fi
    echo "Configuration saved (provider: $provider, host: $host)."
}

cmd_list() {
    [ -d "$REG" ] || die "not configured"
    local f found=0
    printf "%-20s %-6s %-40s %-10s %s\n" "PROJECT" "PHP" "URL" "BRANCH" "AUTODEPLOY"
    for f in "$REG"/*.env; do
        [ -e "$f" ] || break
        found=1
        # shellcheck disable=SC1090
        ( . "$f"; printf "%-20s %-6s %-40s %-10s %s\n" "$NAME" "$PHP" "https://$FQDN" "$BRANCH" "$AUTODEPLOY" )
    done
    [ "$found" = 1 ] || echo "(no projects — use: sudo prj-ai add)"
}

cmd_add() {
    need_root; load_conf

    local name phpv repo branch autodeploy migmaint previews fqdn base sysuser db dbpass
    name=$(ask "Project name / subdomain label (e.g. projectabc)")
    valid_name "$name" || die "invalid name (lowercase, digits, hyphens, max 27 chars)"
    [ -f "$(proj_file "$name")" ] && die "project '$name' already exists"
    fqdn="$name.$BASE_DOMAIN"

    phpv=$(ask "PHP version (${PHP_VERSIONS[*]})" "$DEFAULT_PHP")
    valid_php "$phpv" || die "invalid / not installed PHP version: $phpv"
    repo=$(ask "Git repo URL (https — e.g. $(repo_url_example "${GIT_PROVIDER:-github}"))")
    [ -n "$repo" ] || die "repo is required"
    # Never accept credentials embedded in the URL.
    [[ "$repo" =~ ://[^/]*:[^/]*@ ]] && die "do not put a token/credentials in the repo URL"
    # Azure DevOps is often pasted as https://ORG@dev.azure.com/...: drop the
    # embedded username so the credential store matches by host for everyone.
    repo=$(echo "$repo" | sed -E 's#^(https?://)[^/@]+@#\1#')
    branch=$(ask "Deploy branch" "$DEFAULT_BRANCH")
    autodeploy=$(ask "Auto-deploy (poll every 60s)? (yes/no)" "yes")
    migmaint=$(ask "Maintenance mode during DB migrations? (yes/no)" "no")
    previews=$(ask "Per-developer preview URLs (<user>-$name.$BASE_DOMAIN)? (yes/no)" "yes")

    base="$SRV/$name"; sysuser="prj-$name"
    db="prj_${name//-/_}"; dbpass=$(openssl rand -hex 16)

    echo "==> System user and directories"
    useradd -r -U -M -d "$base" -s /usr/sbin/nologin "$sysuser" 2>/dev/null || true
    mkdir -p "$base/logs" "$base/shared" "$base/releases"

    echo "==> Canonical clone ($repo @ $branch)"
    [ -f "$CRED" ] || die "git token not configured: run 'sudo prj-ai config'"
    git clone --branch "$branch" --no-tags -c credential.helper="store --file=$CRED" "$repo" "$base/repo"
    git -C "$base/repo" config credential.helper "store --file=$CRED"
    git -C "$base/repo" config core.sharedRepository group
    git config --system --add safe.directory "$base/repo"

    echo "==> MySQL database"
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$db'@'localhost' IDENTIFIED BY '$dbpass';
GRANT ALL PRIVILEGES ON \`$db\`.* TO '$db'@'localhost';
FLUSH PRIVILEGES;
SQL

    echo "==> Shared state (.env + storage live outside the releases)"
    local envf="$base/shared/.env"
    if [ ! -f "$envf" ]; then
        [ -f "$base/repo/.env.example" ] && cp "$base/repo/.env.example" "$envf" || touch "$envf"
    fi
    set_env_kv "$envf" APP_ENV staging
    set_env_kv "$envf" APP_URL "https://$fqdn"
    set_env_kv "$envf" DB_CONNECTION mysql
    set_env_kv "$envf" DB_HOST 127.0.0.1
    set_env_kv "$envf" DB_PORT 3306
    set_env_kv "$envf" DB_DATABASE "$db"
    set_env_kv "$envf" DB_USERNAME "$db"
    set_env_kv "$envf" DB_PASSWORD "$dbpass"
    [ -d "$base/shared/storage" ] || cp -a "$base/repo/storage" "$base/shared/storage"

    fix_repo_perms "$name"

    echo "==> First release (composer / npm / artisan, then flip 'current')"
    MIGRATE_MAINTENANCE="$migmaint" REL_TAG=""
    publish_release "$name" "$phpv" first \
        || die "first release build failed for '$name' — inspect $base/logs/deploy.log"
    dlog "$name" "FIRST -> $REL_TAG"

    echo "==> PHP-FPM / Nginx / cron / Supervisor"
    write_fpm_pool "$name" "$phpv";  systemctl reload "php$phpv-fpm"
    write_nginx "$name" "$fqdn";     nginx -t && systemctl reload nginx
    write_cron "$name" "$phpv"
    write_supervisor "$name" "$phpv"; supervisorctl reread >/dev/null; supervisorctl update >/dev/null

    if [ "$LETSENCRYPT" = "yes" ] && [ -n "$LETSENCRYPT_EMAIL" ]; then
        echo "==> Let's Encrypt"
        certbot --nginx -d "$fqdn" --non-interactive --agree-tos -m "$LETSENCRYPT_EMAIL" \
            || warn "certbot failed (DNS not pointed yet?): retry later with: certbot --nginx -d $fqdn"
    fi

    cat > "$(proj_file "$name")" <<EOF
NAME="$name"
FQDN="$fqdn"
PHP="$phpv"
REPO="$repo"
BRANCH="$branch"
DB="$db"
AUTODEPLOY="$autodeploy"
MIGRATE_MAINTENANCE="$migmaint"
PREVIEWS="$previews"
RELEASES_KEEP="3"
CREATED="$(date -Iseconds)"
EOF
    chmod 644 "$(proj_file "$name")"

    if [ "$autodeploy" = "yes" ]; then
        systemctl enable --now "prj-deploy@$name.timer"
    fi

    echo
    echo "Project '$name' created: https://$fqdn"
    echo "Developers will see it in the menu on their next SSH login."
    if [ "$previews" = "yes" ]; then
        if [ -f "$PREV_HTPASSWD" ]; then
            echo "Each developer who opens it also gets https://<user>-$name.$BASE_DOMAIN"
            echo "(needs a wildcard *.$BASE_DOMAIN DNS record)."
        else
            echo "Previews are ON but no Basic Auth is set — run 'sudo prj-ai config' to enable them."
        fi
    fi
}

cmd_del() {
    need_root; load_conf
    local name=${1:-}; [ -n "$name" ] || die "usage: prj-ai del <project>"
    load_proj "$name"

    echo "WARNING: this removes the vhost, queue, cron, canonical checkout and"
    echo "the personal workspaces in /home/*/work/$name (unpushed work is lost)."
    local confirm; confirm=$(ask "Type the project name to confirm")
    [ "$confirm" = "$name" ] || die "aborted"
    local dropdb; dropdb=$(ask "Also drop the database '$DB'? (yes/no)" "no")

    systemctl disable --now "prj-deploy@$name.timer" 2>/dev/null || true
    supervisorctl stop "prj-$name-queue" 2>/dev/null || true
    rm -f "/etc/supervisor/conf.d/prj-$name.conf"; supervisorctl reread >/dev/null; supervisorctl update >/dev/null
    rm -f "/etc/cron.d/prj-$name"
    rm -f "/etc/nginx/sites-enabled/prj-$name" "/etc/nginx/sites-available/prj-$name"
    nginx -t && systemctl reload nginx
    rm -f "/etc/php/$PHP/fpm/pool.d/prj-$name.conf"; systemctl reload "php$PHP-fpm" || true

    if [ "$dropdb" = "yes" ]; then
        mysql <<SQL
DROP DATABASE IF EXISTS \`$DB\`;
DROP USER IF EXISTS '$DB'@'localhost';
SQL
    fi

    # Tear down every developer preview of this project.
    if [ -d "$PREV" ]; then
        local pf pdev pproj
        for pf in "$PREV"/*.env; do
            [ -e "$pf" ] || break
            pdev=$(. "$pf"; echo "${DEV:-}"); pproj=$(. "$pf"; echo "${PROJECT:-}")
            [ "$pproj" = "$name" ] && preview_down "$pdev" "$pproj" --drop-db
        done
    fi

    local h
    for h in /home/*; do
        [ -d "$h/work/$name" ] && rm -rf "$h/work/$name"
    done
    rm -rf "$SRV/$name"
    userdel "prj-$name" 2>/dev/null || true
    rm -f "$(proj_file "$name")"
    echo "Project '$name' deleted."
}

cmd_php() {
    need_root; load_conf
    local name=${1:-} newv=${2:-}
    [ -n "$name" ] || die "usage: prj-ai php <project> [version]"
    load_proj "$name"
    [ -n "$newv" ] || newv=$(ask "New PHP version (${PHP_VERSIONS[*]})" "$DEFAULT_PHP")
    valid_php "$newv" || die "invalid / not installed PHP version: $newv"
    [ "$newv" = "$PHP" ] && { echo "already on PHP $PHP"; return 0; }

    local oldv=$PHP
    rm -f "/etc/php/$oldv/fpm/pool.d/prj-$name.conf"
    write_fpm_pool "$name" "$newv"
    systemctl reload "php$oldv-fpm" || true
    systemctl reload "php$newv-fpm"
    write_cron "$name" "$newv"
    write_supervisor "$name" "$newv"
    supervisorctl reread >/dev/null; supervisorctl update >/dev/null
    supervisorctl restart "prj-$name-queue" >/dev/null || true
    set_env_kv "$(proj_file "$name")" PHP "\"$newv\""

    # Move this project's developer previews onto the new version too.
    if [ -d "$PREV" ]; then
        local pf pdev pproj
        for pf in "$PREV"/*.env; do
            [ -e "$pf" ] || break
            pdev=$(. "$pf"; echo "${DEV:-}"); pproj=$(. "$pf"; echo "${PROJECT:-}")
            [ "$pproj" = "$name" ] && [ -n "$pdev" ] || continue
            rm -f "/etc/php/$oldv/fpm/pool.d/prev-${pdev}-${name}.conf"
            write_preview_pool "$pdev" "$name" "$newv"
            set_env_kv "$pf" PHP "\"$newv\""
        done
        systemctl reload "php$oldv-fpm" 2>/dev/null || true
        systemctl reload "php$newv-fpm" 2>/dev/null || true
    fi
    echo "Project '$name' moved from PHP $oldv to PHP $newv."
    echo "Note: personal workspaces use whichever binary they invoke (php$newv)."
}

cmd_user_add() {
    need_root
    # shellcheck disable=SC1090
    [ -f "$CONF" ] && . "$CONF" || true
    local u pubkey gitname gitmail provider
    provider=$(norm_provider "${GIT_PROVIDER:-github}")
    u=$(ask "Username (e.g. mario)")
    valid_user "$u" || die "invalid username"
    id "$u" >/dev/null 2>&1 && die "user '$u' already exists"

    useradd -m -s /bin/bash -G "$DEV_GROUP" "$u"
    chmod 700 "/home/$u"
    sudo -u "$u" mkdir -p "/home/$u/.ssh" "/home/$u/work"
    chmod 700 "/home/$u/.ssh"

    # The key pair is generated by the DEVELOPER on their own machine
    # (ssh-keygen -t ed25519); paste only the .pub they send you.
    echo "Developer's SSH public key"
    echo "(contents of ~/.ssh/id_ed25519.pub — starts with 'ssh-ed25519 ...')"
    while :; do
        pubkey=$(ask "Paste the key (enter to skip)" "")
        [ -z "$pubkey" ] && break
        if [[ "$pubkey" =~ ^(ssh-ed25519|ssh-rsa|ecdsa-sha2-nistp(256|384|521)|sk-ssh-ed25519@openssh\.com|sk-ecdsa-sha2-nistp256@openssh\.com)[[:space:]] ]]; then
            echo "$pubkey" >> "/home/$u/.ssh/authorized_keys"
            chown "$u:$u" "/home/$u/.ssh/authorized_keys"
            chmod 600 "/home/$u/.ssh/authorized_keys"
            break
        fi
        warn "invalid format: expected an OpenSSH PUBLIC key (e.g. 'ssh-ed25519 AAAA... comment')"
    done

    gitname=$(ask "Name for git commits" "$u")
    gitmail=$(ask "Email for git commits" "$u@$(hostname -f 2>/dev/null || echo local)")
    sudo -u "$u" -H git config --global user.name "$gitname"
    sudo -u "$u" -H git config --global user.email "$gitmail"
    sudo -u "$u" -H git config --global credential.helper store

    # Personal git token — REQUIRED before the developer can open any project
    # (workspace-init verifies the token can read the project repo). Best entered
    # by the developer themselves with 'prj-token' to keep the secret off the
    # admin's screen; may be pasted here if they are onboarding with you.
    local ghost tok0 cred_user
    ghost="${GIT_HOST:-$(provider_host_default "$provider")}"
    echo
    read -rsp "Their personal git token for $ghost (enter to skip — they MUST run 'prj-token' before working): " tok0; echo
    if [ -n "$tok0" ]; then
        cred_user=$(provider_user_default "$provider"); [ -n "$cred_user" ] || cred_user="$u"
        umask 077
        printf 'https://%s:%s@%s\n' "$cred_user" "$tok0" "$ghost" >> "/home/$u/.git-credentials"
        chown "$u:$u" "/home/$u/.git-credentials"; chmod 600 "/home/$u/.git-credentials"
        echo "Token stored in /home/$u/.git-credentials (dev-only, mode 600)."
    fi

    cat <<EOF

User '$u' created. Send the developer:
  1. ssh $u@$(hostname -f 2>/dev/null || hostname)
  2. run 'prj-token' FIRST — save THEIR OWN personal token
     ($(provider_token_hint "$provider")). REQUIRED: a project will not open until
     their token can see that project's repository.
  3. pick a project from the menu (workspace + preview are created automatically)
  4. inside tmux: run 'claude' and log in with THEIR OWN Claude plan
  5. open a PR/MR from the workspace with:  prj-pr "Title" [--target <branch>] [--draft]
     prj-pr works for GitHub, GitLab, Bitbucket and Azure DevOps.
EOF
    case "$provider" in
        github)
            echo "     'gh auth login' + 'gh pr create' also work. Larapilot's 'github' toggle"
            echo "     opens PRs by itself via gh when enabled in project settings." ;;
        gitlab)
            echo "     'glab auth login' + 'glab mr create' also work. Larapilot's 'gitlab' toggle"
            echo "     opens MRs by itself via glab when enabled in project settings." ;;
        bitbucket)
            echo "     Bitbucket has no CLI: prj-pr uses the REST API. Larapilot's 'bitbucket'"
            echo "     toggle can also open PRs when LARAPILOT_BITBUCKET_* is set in the workspace .env." ;;
        azure)
            echo "     'az devops login' also works. Larapilot's 'azure' toggle can open PRs when"
            echo "     LARAPILOT_AZURE_DEVOPS_PAT is set in the workspace .env." ;;
    esac
}

cmd_user_del() {
    need_root
    local u=${1:-}; [ -n "$u" ] || die "usage: prj-ai user-del <user>"
    id "$u" >/dev/null 2>&1 || die "user '$u' does not exist"
    id -nG "$u" | grep -qw "$DEV_GROUP" || die "'$u' is not a prj-ai developer user"
    local confirm; confirm=$(ask "Type the username to confirm deletion (home included)")
    [ "$confirm" = "$u" ] || die "aborted"

    # Tear down every preview this developer owns (drop their preview DBs too).
    if [ -d "$PREV" ]; then
        local pf pdev pproj
        for pf in "$PREV"/*.env; do
            [ -e "$pf" ] || break
            pdev=$(. "$pf"; echo "${DEV:-}"); pproj=$(. "$pf"; echo "${PROJECT:-}")
            [ "$pdev" = "$u" ] && preview_down "$u" "$pproj" --drop-db
        done
    fi

    pkill -u "$u" 2>/dev/null || true
    sleep 1
    pkill -9 -u "$u" 2>/dev/null || true
    userdel -r "$u"
    echo "User '$u' deleted."
}

cmd_deploy() {
    need_root; load_conf
    local name=${1:-}; shift || true
    [ -n "$name" ] || die "usage: prj-ai deploy <project> [--auto]"
    local auto=no a
    for a in "$@"; do [ "$a" = "--auto" ] && auto=yes; done
    load_proj "$name"

    local repo="$SRV/$name/repo" head remote changed
    git -C "$repo" fetch --quiet --prune --no-tags origin "$BRANCH"
    head=$(git -C "$repo" rev-parse HEAD)
    remote=$(git -C "$repo" rev-parse "origin/$BRANCH")
    if [ "$head" = "$remote" ]; then
        [ "$auto" = yes ] || echo "already up to date ($head)"
        return 0
    fi

    # Update the source checkout (repo/ is never served, so this is safe), then
    # build + flip a fresh release. publish_release leaves 'current' untouched on
    # any failure, so a broken build never takes the site down.
    changed=$(git -C "$repo" diff --name-only "$head" "$remote" || true)
    git -C "$repo" merge --ff-only "origin/$BRANCH" || git -C "$repo" reset --hard "origin/$BRANCH"
    fix_repo_perms "$name"

    REL_TAG=""
    if ! publish_release "$name" "$PHP" deploy "$changed"; then
        dlog "$name" "FAILED ${head:0:8} -> ${remote:0:8} (release aborted — previous release still live)"
        die "deploy failed for '$name' — the previous release is still serving traffic (see $SRV/$name/logs/deploy.log)"
    fi

    # New release is live. Restart the queue worker so it runs the new code too.
    supervisorctl restart "prj-$name-queue" >/dev/null 2>&1 || true
    dlog "$name" "OK ${head:0:8} -> ${remote:0:8} ($REL_TAG)"
    [ "$auto" = yes ] || echo "deploy '$name': ${head:0:8} -> ${remote:0:8}  [release $REL_TAG, zero-downtime]"
}

cmd_rollback() {
    need_root; load_conf
    local name=${1:-}; [ -n "$name" ] || die "usage: prj-ai rollback <project>"
    load_proj "$name"
    local base="$SRV/$name" cur prev
    cur=$(current_release "$name")
    prev=$(prev_release "$name")
    [ -n "$prev" ] && [ -d "$prev" ] || die "no previous release to roll back to (releases are pruned to RELEASES_KEEP=${RELEASES_KEEP:-3})"

    ln -sfn "$prev" "$base/current.new"
    mv -T "$base/current.new" "$base/current" || { rm -f "$base/current.new"; die "could not flip 'current'"; }
    chown -h "prj-$name:$DEV_GROUP" "$base/current" 2>/dev/null || true
    supervisorctl restart "prj-$name-queue" >/dev/null 2>&1 || true
    dlog "$name" "ROLLBACK $(basename "${cur:-?}") -> $(basename "$prev")"
    echo "rolled back '$name': now serving $(basename "$prev")"
    echo "NOTE: database migrations are NOT reverted — if the rolled-back release"
    echo "      predates a schema change you must undo that migration by hand."
}

# Create (if missing) or refresh the personal workspace of the user invoking
# this via sudo. Idempotent and fast when the workspace already exists.
cmd_workspace_init() {
    need_root
    # shellcheck disable=SC1090
    [ -f "$CONF" ] && . "$CONF" || true
    local name=${1:-}; [ -n "$name" ] || die "usage: prj-ai workspace-init <project>"
    load_proj "$name"

    # Only the invoking developer is a valid target: no dev can provision into
    # another dev's home.
    local u=${SUDO_USER:-}
    [ -n "$u" ] && [ "$u" != "root" ] || die "invoke via sudo as a developer"
    id -nG "$u" | grep -qw "$DEV_GROUP" || die "'$u' is not in group $DEV_GROUP"

    local home ws
    home=$(getent passwd "$u" | cut -d: -f6)
    ws="$home/work/$name"

    # MANDATORY: the developer's OWN git token must be able to see this project's
    # repository. No workspace (and no preview) is created until it can — so a
    # developer cannot open a project their token has no access to.
    [ -s "$home/.git-credentials" ] \
        || die "no personal git token yet — run 'prj-token' first (it must have access to $REPO)"
    if ! sudo -u "$u" -H env GIT_TERMINAL_PROMPT=0 git ls-remote --heads "$REPO" >/dev/null 2>&1; then
        die "your git token cannot access $REPO
       -> run 'prj-token' with a token that includes this repository, then reopen the project"
    fi

    if [ ! -d "$ws/.git" ]; then
        echo "==> Creating workspace $ws (clone from the local canonical)"
        sudo -u "$u" -H mkdir -p "$home/work"
        sudo -u "$u" -H git clone "$SRV/$name/repo" "$ws"
        sudo -u "$u" -H git -C "$ws" remote rename origin canonical
        sudo -u "$u" -H git -C "$ws" remote add origin "$REPO"
        # Seed the workspace .env from the project's shared .env (staging values);
        # the developer edits their own copy freely — it is never deployed.
        if [ -f "$SRV/$name/shared/.env" ]; then
            install -o "$u" -g "$u" -m 600 "$SRV/$name/shared/.env" "$ws/.env"
        fi
        echo "==> composer install (first run, may take a minute)"
        sudo -u "$u" -H bash -c "cd '$ws' && php$PHP /usr/local/bin/composer install --no-interaction --prefer-dist --no-progress" \
            || warn "composer install failed: rerun it from the workspace"
    fi

    # Claude Code guardrails: deny access to canonical checkouts, system config,
    # credentials and OTHER workspaces. Regenerated on every login.
    local deny=(
        "Read(//srv/**)"  "Edit(//srv/**)"  "Write(//srv/**)"
        "Read(//etc/prj-ai/**)"
        "Read(~/.ssh/**)" "Read(~/.git-credentials)"
        "Read(~/.claude/**)" "Edit(~/.claude/**)"
    )
    local f p
    for f in "$REG"/*.env; do
        [ -e "$f" ] || break
        p=$(basename "$f" .env)
        [ "$p" = "$name" ] && continue
        deny+=("Read(~/work/$p/**)" "Edit(~/work/$p/**)" "Write(~/work/$p/**)")
    done
    sudo -u "$u" -H mkdir -p "$ws/.claude"
    printf '%s\n' "${deny[@]}" | jq -R . | jq -s '{permissions: {deny: .}}' \
        | sudo -u "$u" -H tee "$ws/.claude/settings.local.json" >/dev/null
    grep -qxF '.claude/settings.local.json' "$ws/.git/info/exclude" 2>/dev/null \
        || echo '.claude/settings.local.json' | sudo -u "$u" -H tee -a "$ws/.git/info/exclude" >/dev/null

    # Tell Claude to use prj-pr for PRs/MRs on every provider. CLAUDE.local.md is
    # personal, auto-loaded by Claude Code, and git-excluded. We only manage it
    # while it still carries our marker on the first line.
    local marker="<!-- managed-by: prj-ai -->" cmd="$ws/CLAUDE.local.md"
    if [ ! -f "$cmd" ] || head -n1 "$cmd" 2>/dev/null | grep -qF "$marker"; then
        sudo -u "$u" -H tee "$cmd" >/dev/null <<EOF
$marker
## Pull / Merge Requests on this VPS

To open a PR/MR from this workspace, always run:

    prj-pr "Title" [--target <branch>] [--draft] [--desc "text"]

It detects the provider (GitHub / GitLab / Bitbucket / Azure DevOps) from the
\`origin\` remote, pushes the current branch, and opens the request against the
project's deploy branch. It uses the personal token saved by \`prj-token\`
(or prompted on the first push) so every commit and PR is attributed to this
developer.

\`gh\` (GitHub) and \`glab\` (GitLab) also work directly after \`gh auth login\` /
\`glab auth login\`. \`prj-pr\` is the provider-agnostic path and the only one for
Bitbucket and Azure DevOps from a plain shell.
EOF
        grep -qxF 'CLAUDE.local.md' "$ws/.git/info/exclude" 2>/dev/null \
            || echo 'CLAUDE.local.md' | sudo -u "$u" -H tee -a "$ws/.git/info/exclude" >/dev/null
    fi

    # Per-developer preview URL (<user>-<project>.<domain>) when the project
    # opted in and Basic Auth credentials exist. Cheap on repeat logins.
    if [ "${PREVIEWS:-no}" = yes ] && [ -f "$PREV_HTPASSWD" ]; then
        preview_up "$u" "$name" "$PHP" || warn "preview provisioning failed for $u/$name"
    elif [ "${PREVIEWS:-no}" = yes ]; then
        warn "previews are ON for '$name' but no Basic Auth is set — run 'sudo prj-ai config'"
    fi
}

cmd_preview() {
    need_root
    # shellcheck disable=SC1090
    [ -f "$CONF" ] && . "$CONF" || true
    local sub=${1:-list}; shift || true
    case "$sub" in
        list)
            [ -d "$PREV" ] && ls "$PREV"/*.env >/dev/null 2>&1 || { echo "(no previews)"; return 0; }
            printf "%-34s %-10s %-14s %s\n" "URL" "DEVELOPER" "PROJECT" "LAST SEEN"
            local f
            for f in "$PREV"/*.env; do
                [ -e "$f" ] || break
                # shellcheck disable=SC1090
                ( . "$f"
                  seen="never"
                  [ -f "/var/log/nginx/prev-${ID:-x}.log" ] \
                      && seen=$(date -Is -r "/var/log/nginx/prev-$ID.log" 2>/dev/null || echo "?")
                  printf "%-34s %-10s %-14s %s\n" "https://${FQDN:-?}" "${DEV:-?}" "${PROJECT:-?}" "$seen" ) || true
            done ;;
        up)
            local u=${1:-} p=${2:-}
            [ -n "$u" ] && [ -n "$p" ] || die "usage: prj-ai preview up <user> <project>"
            load_conf; load_proj "$p"
            id "$u" >/dev/null 2>&1 || die "no such user: $u"
            [ -d "/home/$u/work/$p/.git" ] || die "$u has not opened '$p' yet (no workspace)"
            preview_up "$u" "$p" "$PHP" ;;
        down)
            local u=${1:-} p=${2:-} d=${3:-}
            [ -n "$u" ] && [ -n "$p" ] || die "usage: prj-ai preview down <user> <project> [--drop-db]"
            preview_down "$u" "$p" "$d" ;;
        reap)
            preview_reap ;;
        *)
            die "usage: prj-ai preview {list | up <user> <project> | down <user> <project> [--drop-db] | reap}" ;;
    esac
}

usage() {
    sed -n '3,20p' "$0" | sed 's/^# \{0,1\}//'
}

case "${1:-help}" in
    config)          cmd_config ;;
    list)            cmd_list ;;
    add)             cmd_add ;;
    del)             shift; cmd_del "$@" ;;
    php)             shift; cmd_php "$@" ;;
    user-add)        cmd_user_add ;;
    user-del)        shift; cmd_user_del "$@" ;;
    deploy)          shift; cmd_deploy "$@" ;;
    rollback)        shift; cmd_rollback "$@" ;;
    preview)         shift; cmd_preview "$@" ;;
    workspace-init)  shift; cmd_workspace_init "$@" ;;
    help|--help|-h)  usage ;;
    *)               usage; exit 1 ;;
esac
EMBED_PRJ_AI
chmod 0755 /usr/local/sbin/prj-ai

cat > /usr/local/bin/prj-work <<'EMBED_PRJ_WORK'
#!/usr/bin/env bash
#
# prj-work — project menu for developers
#
#   prj-work              interactive menu
#   prj-work <project>    open a project directly
#
# Selecting a project:
#   1. the personal workspace ~/work/<project> is created if missing
#      (clone from the local canonical checkout) or reused if present
#   2. you enter a tmux session named after the project: if it already
#      exists (e.g. after a disconnect) you RE-ATTACH, so running tasks
#      are never lost.
#
set -u

REG=/etc/prj-ai/projects

projects() {
    local f
    for f in "$REG"/*.env; do
        [ -e "$f" ] || return 0
        basename "$f" .env
    done
}

open_project() {
    local p=$1
    [ -f "$REG/$p.env" ] || { echo "prj-work: project '$p' does not exist" >&2; return 1; }
    # Provision/refresh the workspace (root, allowed via sudoers). This also
    # brings up the developer's preview URL when the project opted in.
    # workspace-init prints a specific reason on failure (e.g. the git token
    # cannot see this project) — let it speak, just don't drop into tmux.
    sudo -n /usr/local/sbin/prj-ai workspace-init "$p" || return 1
    local pf="/etc/prj-ai/previews/${USER}-${p}.env"
    [ -f "$pf" ] && echo "  preview: https://$(. "$pf" 2>/dev/null; echo "${FQDN:-}")"
    # -A: attach if the session exists, otherwise create it in the workspace.
    exec tmux new-session -A -s "$p" -c "$HOME/work/$p"
}

if [ $# -ge 1 ]; then
    open_project "$1"
    exit $?
fi

mapfile -t list < <(projects)
if [ ${#list[@]} -eq 0 ]; then
    echo "No projects available (an administrator must run: sudo prj-ai add)."
    exit 0
fi

echo
echo "=== Available projects ==="
active=$(tmux ls -F '#S' 2>/dev/null || true)
i=1
for p in "${list[@]}"; do
    mark=""
    echo "$active" | grep -qx "$p" && mark="  << active session: resume where you were"
    printf "  %d) %s%s\n" "$i" "$p" "$mark"
    i=$((i + 1))
done
echo "  0) plain shell (no project)"
echo

read -rp "Choice: " choice
case "$choice" in
    0|"") exit 0 ;;
    *[!0-9]*) echo "Invalid choice"; exit 1 ;;
    *)
        idx=$((choice - 1))
        [ "$idx" -ge 0 ] && [ "$idx" -lt ${#list[@]} ] || { echo "Invalid choice"; exit 1; }
        open_project "${list[$idx]}"
        ;;
esac
EMBED_PRJ_WORK
chmod 0755 /usr/local/bin/prj-work

cat > /usr/local/bin/prj-pr <<'EMBED_PRJ_PR'
#!/usr/bin/env bash
#
# prj-pr — open a Pull/Merge Request from the current workspace
#
# Usage: prj-pr ["Title"] [--target <branch>] [--draft] [--desc "text"]
#
#   - provider (GitHub / GitLab / Bitbucket / Azure DevOps) is detected from
#     the 'origin' remote
#   - the current branch is pushed, then the request is opened against
#     --target (default: the project's deploy branch from /etc/prj-ai)
#   - auth: gh/glab if authenticated, otherwise the personal token already
#     saved in ~/.git-credentials on the first push
#
set -euo pipefail

REG=/etc/prj-ai/projects
die() { echo "prj-pr: $*" >&2; exit 1; }

top=$(git rev-parse --show-toplevel 2>/dev/null) || die "not inside a git repository"
url=$(git -C "$top" remote get-url origin 2>/dev/null) || die "no 'origin' remote"
branch=$(git -C "$top" rev-parse --abbrev-ref HEAD)
[ "$branch" != HEAD ] || die "detached HEAD: check out a branch"

title=""; target=""; desc=""; draft=no
while [ $# -gt 0 ]; do
    case "$1" in
        --target) target=${2:?}; shift 2 ;;
        --desc)   desc=${2:?};   shift 2 ;;
        --draft)  draft=yes;     shift ;;
        -h|--help) sed -n '3,13p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) title=$1; shift ;;
    esac
done

name=$(basename "$top")
if [ -z "$target" ] && [ -f "$REG/$name.env" ]; then
    target=$(. "$REG/$name.env"; echo "${BRANCH:-}")
fi
[ -n "$target" ] || target=main
[ "$branch" != "$target" ] || die "already on '$target': create a feature branch first"
[ -n "$title" ] || title=$(git -C "$top" log -1 --pretty=%s)

cred_token() {   # host -> token from ~/.git-credentials (user:token@host)
    [ -f "$HOME/.git-credentials" ] || return 0
    sed -nE "s#^https://[^:]+:([^@]+)@${1//./\\.}(/.*)?\$#\1#p" "$HOME/.git-credentials" | head -n1
}
cred_userpass() { # host -> "user:token"
    [ -f "$HOME/.git-credentials" ] || return 0
    sed -nE "s#^https://([^@]+)@${1//./\\.}(/.*)?\$#\1#p" "$HOME/.git-credentials" | head -n1
}

echo "==> pushing '$branch' to origin"
git -C "$top" push -u origin "$branch"

case "$url" in
    *github.com*)
        slug=$(echo "$url" | sed -E 's#^(https?://|git@)##; s#github.com[:/]##; s#\.git$##; s#/$##')
        if command -v gh >/dev/null 2>&1 && gh auth status >/dev/null 2>&1; then
            args=(pr create --base "$target" --head "$branch" --title "$title" --body "${desc:-}")
            [ "$draft" = yes ] && args+=(--draft)
            gh "${args[@]}"
        else
            tok=$(cred_token github.com); [ -n "$tok" ] || die "no GitHub token in ~/.git-credentials and gh not authenticated"
            payload=$(jq -n --arg t "$title" --arg h "$branch" --arg b "$target" --arg d "$desc" \
                --argjson dr "$([ "$draft" = yes ] && echo true || echo false)" \
                '{title:$t,head:$h,base:$b,body:$d,draft:$dr}')
            curl -fsSL -X POST -H "Authorization: token $tok" -H "Accept: application/vnd.github+json" \
                "https://api.github.com/repos/$slug/pulls" -d "$payload" \
                | jq -r '"PR #\(.number): \(.html_url)"'
        fi
        ;;
    *gitlab*)
        host=$(echo "$url" | sed -E 's#^https?://##; s#^git@##; s#[:/].*##')
        proj=$(echo "$url" | sed -E 's#^https?://[^/]+/##; s#^git@[^:]+:##; s#\.git$##; s#/$##')
        if command -v glab >/dev/null 2>&1 && glab auth status >/dev/null 2>&1; then
            args=(mr create --source-branch "$branch" --target-branch "$target" \
                --title "$title" --description "${desc:-$title}" --yes)
            [ "$draft" = yes ] && args+=(--draft)
            glab "${args[@]}"
        else
            tok=$(cred_token "$host"); [ -n "$tok" ] || die "no GitLab token in ~/.git-credentials and glab not authenticated"
            pid=$(printf '%s' "$proj" | jq -sRr @uri)
            payload=$(jq -n --arg s "$branch" --arg t "$target" --arg ti "$title" --arg d "$desc" \
                '{source_branch:$s,target_branch:$t,title:$ti,description:$d}')
            curl -fsSL -X POST -H "PRIVATE-TOKEN: $tok" -H "Content-Type: application/json" \
                "https://$host/api/v4/projects/$pid/merge_requests" -d "$payload" \
                | jq -r '"MR !\(.iid): \(.web_url)"'
        fi
        ;;
    *bitbucket.org*)
        slug=$(echo "$url" | sed -E 's#^(https?://|git@)##; s#bitbucket.org[:/]##; s#\.git$##; s#/$##')
        up=$(cred_userpass bitbucket.org); [ -n "$up" ] || die "no 'user:app-password' for bitbucket.org in ~/.git-credentials"
        payload=$(jq -n --arg t "$title" --arg s "$branch" --arg d "$target" --arg de "$desc" \
            '{title:$t,source:{branch:{name:$s}},destination:{branch:{name:$d}},description:$de}')
        curl -fsSL -u "$up" -X POST -H "Content-Type: application/json" \
            "https://api.bitbucket.org/2.0/repositories/$slug/pullrequests" -d "$payload" \
            | jq -r '"PR #\(.id): \(.links.html.href)"'
        ;;
    *dev.azure.com*|*visualstudio.com*)
        [[ "$url" =~ dev\.azure\.com/([^/]+)/([^/]+)/_git/([^/]+)/?$ ]] \
            || die "unrecognised Azure DevOps URL: $url"
        org=${BASH_REMATCH[1]}; project_enc=${BASH_REMATCH[2]}; repo=${BASH_REMATCH[3]%.git}
        project=${project_enc//%20/ }
        if [ -z "${AZURE_DEVOPS_EXT_PAT:-}" ]; then
            tok=$(cred_token dev.azure.com); [ -n "$tok" ] && export AZURE_DEVOPS_EXT_PAT="$tok" || true
        fi
        az extension show --name azure-devops >/dev/null 2>&1 || az extension add --name azure-devops >/dev/null
        args=(repos pr create
            --organization "https://dev.azure.com/$org"
            --project "$project" --repository "$repo"
            --source-branch "$branch" --target-branch "$target" --title "$title")
        [ -n "$desc" ]     && args+=(--description "$desc")
        [ "$draft" = yes ] && args+=(--draft true)
        pr_id=$(az "${args[@]}" --output json | jq -r '.pullRequestId')
        echo "PR #$pr_id: https://dev.azure.com/$org/$project_enc/_git/$repo/pullrequest/$pr_id"
        ;;
    *)
        die "unsupported git host in origin: $url"
        ;;
esac
EMBED_PRJ_PR
chmod 0755 /usr/local/bin/prj-pr

cat > /usr/local/bin/prj-token <<'EMBED_PRJ_TOKEN'
#!/usr/bin/env bash
#
# prj-token — save YOUR personal git token on this server
#
# REQUIRED before you can open any project: the menu verifies your token can see
# the project's repository and refuses to create the workspace otherwise. Use a
# token that includes every project you work on.
#
# Pushes and `prj-pr` then authenticate as you, and the git provider attributes
# every commit / PR / MR to your account. Your token is written only to
# ~/.git-credentials (mode 600, your home) and is never seen by other users or
# by the service account used for deploys.
#
# Run it once after your first login, and again whenever the token expires or
# you gain access to a new project.
#
set -euo pipefail

CONF=/etc/prj-ai/prj-ai.conf
[ -r "$CONF" ] || { echo "prj-token: server not configured yet — ask your admin." >&2; exit 1; }
# shellcheck disable=SC1090
. "$CONF"

host="${GIT_HOST:-github.com}"
provider="${GIT_PROVIDER:-github}"
case "$provider" in
    github)    cred_user=x-access-token; hint="GitHub PAT — classic scope 'repo', or fine-grained Contents Read & Write" ;;
    gitlab)    cred_user=oauth2;         hint="GitLab PAT — scopes read_repository, write_repository" ;;
    bitbucket) cred_user="";             hint="Bitbucket App Password — Repositories + Pull requests, Read & Write" ;;
    azure)     cred_user=pat;            hint="Azure DevOps PAT — scope Code (Read & Write)" ;;
    *)         cred_user=x-access-token; hint="personal access token" ;;
esac

echo "Provider: $provider   Host: $host"
echo "Token:    $hint"
echo
if [ -z "$cred_user" ]; then
    read -rp "Your $provider username: " cred_user
    [ -n "$cred_user" ] || { echo "prj-token: a username is required for $provider." >&2; exit 1; }
fi
read -rsp "Paste your token (input hidden): " tok; echo
[ -n "$tok" ] || { echo "prj-token: nothing entered — no change." >&2; exit 1; }

cred="$HOME/.git-credentials"
umask 077
tmp=$(mktemp "${cred}.XXXXXX")
if [ -f "$cred" ]; then
    grep -vE "@${host//./\\.}(/|\$)" "$cred" > "$tmp" || true
fi
printf 'https://%s:%s@%s\n' "$cred_user" "$tok" "$host" >> "$tmp"
mv "$tmp" "$cred"
chmod 600 "$cred"
git config --global credential.helper store

name=$(git config --global user.name  || echo '?')
mail=$(git config --global user.email || echo '?')
echo
echo "Saved for $host. Pushes and 'prj-pr' now authenticate as you."
echo "Commits are attributed to: $name <$mail>"
echo "(ask the admin to fix the identity with 'prj-ai' if that is wrong)."
EMBED_PRJ_TOKEN
chmod 0755 /usr/local/bin/prj-token

cat > /etc/profile.d/zz-prj-ai.sh <<'EMBED_PROFILE'
# prj-ai — project menu at SSH login for developers (group prjdev).
# Skipped when: non-interactive session (scp/rsync/CI), already inside tmux,
# VS Code Remote terminal, or PRJ_NO_MENU=1.
case $- in
    *i*)
        if [ -n "${SSH_CONNECTION:-}" ] && [ -z "${TMUX:-}" ] && [ -t 0 ] \
            && [ "${TERM_PROGRAM:-}" != "vscode" ] && [ -z "${PRJ_NO_MENU:-}" ]; then
            if id -nG 2>/dev/null | grep -qw prjdev; then
                prj-work || true
            fi
        fi
        ;;
esac
EMBED_PROFILE
chmod 0644 /etc/profile.d/zz-prj-ai.sh

# Developers may invoke ONLY their own workspace provisioning.
cat > /etc/sudoers.d/prj-ai <<'EOF'
%prjdev ALL=(root) NOPASSWD: /usr/local/sbin/prj-ai workspace-init *
EOF
chmod 440 /etc/sudoers.d/prj-ai
visudo -cf /etc/sudoers.d/prj-ai >/dev/null

echo "==> systemd units for auto-deploy (optional, per project)"
cat > /etc/systemd/system/prj-deploy@.service <<'EOF'
[Unit]
Description=prj-ai auto-deploy %i
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
Nice=10
IOSchedulingClass=idle
# A build that hangs must not wedge the timer forever. On failure the unit is
# marked failed (visible in 'systemctl status prj-deploy@<project>') and the
# previous release keeps serving — publish_release never touches 'current'
# unless every build step succeeded.
TimeoutStartSec=900
ExecStart=/usr/local/sbin/prj-ai deploy %i --auto
EOF
cat > /etc/systemd/system/prj-deploy@.timer <<'EOF'
[Unit]
Description=prj-ai auto-deploy timer %i

[Timer]
OnBootSec=2min
OnUnitActiveSec=60
AccuracySec=20s
RandomizedDelaySec=15s

[Install]
WantedBy=timers.target
EOF

echo "==> systemd unit for the preview reaper (daily)"
cat > /etc/systemd/system/prj-preview-reap.service <<'EOF'
[Unit]
Description=prj-ai — tear down stale developer previews

[Service]
Type=oneshot
Nice=15
IOSchedulingClass=idle
ExecStart=/usr/local/sbin/prj-ai preview reap
EOF
cat > /etc/systemd/system/prj-preview-reap.timer <<'EOF'
[Unit]
Description=prj-ai preview reaper (daily)

[Timer]
OnCalendar=daily
Persistent=true
RandomizedDelaySec=30min

[Install]
WantedBy=timers.target
EOF
systemctl daemon-reload
systemctl enable --now prj-preview-reap.timer >/dev/null 2>&1 || true

echo "==> Firewall"
ufw allow OpenSSH >/dev/null
ufw allow 'Nginx Full' >/dev/null
ufw --force enable >/dev/null

cat <<'EOF'

============================================================
 Provisioning complete.

 Next steps:
   1. sudo prj-ai config      # base domain, git provider, service token, defaults
   2. sudo prj-ai user-add    # one user per developer
   3. sudo prj-ai add         # one project per repo

 Recommended: disable SSH password login
 (PasswordAuthentication no in /etc/ssh/sshd_config) once the
 developers' public keys are loaded.
============================================================
EOF
