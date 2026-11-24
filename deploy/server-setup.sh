#!/usr/bin/env bash
# =============================================================================
# FGRE - one-time server setup (Ubuntu 24.04 LTS, run as root).
#
# Installs: nginx, PHP 8.4 (FPM + extensions), PostgreSQL 18, Node.js 22 LTS,
# Composer, xpdf pdftotext, supervisor; creates the database, the queue worker,
# the scheduler cron and the nginx site; clones the repository.
#
#   sudo DOMAIN=reports.example.com DB_PASSWORD='a-strong-password' \
#        REPO=https://github.com/fandaqah/FGRE-Fandaqah-Group-Reporting-Engine.git \
#        BRANCH=main bash deploy/server-setup.sh
#
# Safe to re-run: every step checks what is already there.
# Then: put the .env in place (deploy/production.env.example) and run deploy/deploy.sh.
# =============================================================================
set -euo pipefail

DOMAIN="${DOMAIN:?Set DOMAIN, e.g. reports.example.com}"
DB_PASSWORD="${DB_PASSWORD:?Set DB_PASSWORD for the fgre database user}"
REPO="${REPO:-https://github.com/fandaqah/FGRE-Fandaqah-Group-Reporting-Engine.git}"
BRANCH="${BRANCH:-main}"
APP_DIR="${APP_DIR:-/var/www/fgre}"
APP_USER="${APP_USER:-www-data}"
PHP="${PHP:-8.4}"
PG="${PG:-18}"
XPDF_VERSION="${XPDF_VERSION:-4.06}"   # check https://www.xpdfreader.com/download.html for the latest

log() { printf '\n\033[1;31m==> %s\033[0m\n' "$*"; }
[ "$(id -u)" -eq 0 ] || { echo "Run as root (sudo)."; exit 1; }

log "Base packages"
apt-get update -y
apt-get install -y software-properties-common ca-certificates curl gnupg lsb-release unzip git nginx supervisor cron

log "PHP ${PHP}"
if ! command -v "php${PHP}" >/dev/null; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update -y
fi
apt-get install -y "php${PHP}-fpm" "php${PHP}-cli" "php${PHP}-pgsql" "php${PHP}-intl" "php${PHP}-mbstring" \
    "php${PHP}-bcmath" "php${PHP}-xml" "php${PHP}-curl" "php${PHP}-zip"
# Report uploads up to 25 MB
sed -i 's/^upload_max_filesize.*/upload_max_filesize = 30M/; s/^post_max_size.*/post_max_size = 32M/' "/etc/php/${PHP}/fpm/php.ini"
systemctl enable --now "php${PHP}-fpm"

log "PostgreSQL ${PG}"
if [ ! -f /etc/apt/sources.list.d/pgdg.list ]; then
    install -d /usr/share/postgresql-common/pgdg
    curl -fsSL -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc https://www.postgresql.org/media/keys/ACCC4CF8.asc
    echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" \
        > /etc/apt/sources.list.d/pgdg.list
    apt-get update -y
fi
apt-get install -y "postgresql-${PG}"
systemctl enable --now postgresql
sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='fgre'" | grep -q 1 \
    || sudo -u postgres psql -c "CREATE ROLE fgre LOGIN PASSWORD '${DB_PASSWORD}'"
sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='fgre'" | grep -q 1 \
    || sudo -u postgres createdb -O fgre -E UTF8 fgre

log "Node.js 22 LTS (builds the frontend during deploys)"
if ! command -v node >/dev/null || [ "$(node -p 'process.versions.node.split(".")[0]')" -lt 22 ]; then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    apt-get install -y nodejs
fi

log "Composer"
command -v composer >/dev/null || curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

log "xpdf pdftotext ${XPDF_VERSION} (reads report PDFs; the Poppler version will not work)"
if ! /usr/local/bin/pdftotext -h 2>&1 | grep -q -- '-table'; then
    tmp="$(mktemp -d)"
    curl -fsSL "https://dl.xpdfreader.com/xpdf-tools-linux-${XPDF_VERSION}.tar.gz" -o "$tmp/xpdf.tgz"
    tar -xzf "$tmp/xpdf.tgz" -C "$tmp"
    install -m 755 "$tmp/xpdf-tools-linux-${XPDF_VERSION}/bin64/pdftotext" /usr/local/bin/pdftotext
    rm -rf "$tmp"
fi
/usr/local/bin/pdftotext -v 2>&1 | head -1

log "Application directory ${APP_DIR}"
if [ ! -d "${APP_DIR}/.git" ]; then
    install -d -o "$APP_USER" -g "$APP_USER" "$APP_DIR"
    sudo -u "$APP_USER" -H git clone --branch "$BRANCH" "$REPO" "$APP_DIR"
fi
install -d -o "$APP_USER" -g "$APP_USER" /var/backups/fgre

log "nginx site"
sed "s/__DOMAIN__/${DOMAIN}/g; s#__APP_DIR__#${APP_DIR}#g; s/__PHP__/${PHP}/g" "${APP_DIR}/deploy/nginx/fgre.conf" > /etc/nginx/sites-available/fgre
ln -sf /etc/nginx/sites-available/fgre /etc/nginx/sites-enabled/fgre
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

log "Queue worker (supervisor)"
sed "s#__APP_DIR__#${APP_DIR}#g; s/__APP_USER__/${APP_USER}/g" "${APP_DIR}/deploy/supervisor/fgre-worker.conf" > /etc/supervisor/conf.d/fgre-worker.conf
supervisorctl reread && supervisorctl update

log "Scheduler and nightly backup (cron)"
sed "s#__APP_DIR__#${APP_DIR}#g; s/__APP_USER__/${APP_USER}/g" "${APP_DIR}/deploy/cron/fgre" > /etc/cron.d/fgre
chmod 644 /etc/cron.d/fgre

cat <<EOF

Server ready. Next:
  1. HTTPS:  apt-get install -y certbot python3-certbot-nginx && certbot --nginx -d ${DOMAIN}
  2. Copy deploy/production.env.example to ${APP_DIR}/.env, fill in every CHANGE_ME
     (DB_PASSWORD is the one you gave this script), then:
        chown ${APP_USER}:${APP_USER} ${APP_DIR}/.env && chmod 640 ${APP_DIR}/.env
  3. First deploy:  sudo BRANCH=${BRANCH} bash ${APP_DIR}/deploy/deploy.sh
EOF
