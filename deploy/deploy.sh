#!/usr/bin/env bash
# =============================================================================
# FGRE - deploy a release (run as root or a sudoer, on the server).
#
#   sudo BRANCH=main bash /var/www/fgre/deploy/deploy.sh
#
# Production deploys `main`, a staging server deploys `staging`. The site shows a
# maintenance page for the minute the deploy takes; the queue worker restarts on
# the new code. Any step that fails stops the script and leaves maintenance on
# (bring it back with: sudo -u www-data php /var/www/fgre/artisan up).
# =============================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/fgre}"
APP_USER="${APP_USER:-www-data}"
BRANCH="${BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
PHP_FPM="${PHP_FPM:-php8.4-fpm}"

log() { printf '\n\033[1;31m==> %s\033[0m\n' "$*"; }
as_app() { sudo -u "$APP_USER" -H bash -c "cd '$APP_DIR' && $*"; }
trap 'echo; echo "DEPLOY FAILED at line $LINENO. The site is still in maintenance mode; fix and re-run, or: sudo -u $APP_USER $PHP_BIN $APP_DIR/artisan up"' ERR

cd "$APP_DIR"
[ -f .env ] || { echo "Missing $APP_DIR/.env (see deploy/production.env.example)."; exit 1; }
grep -q 'CHANGE_ME' .env && { echo ".env still contains CHANGE_ME values."; exit 1; }

log "Checking the PDF reader tool"
PDFTOTEXT="$(grep -E '^FGRE_PDFTOTEXT=' .env | cut -d= -f2- || true)"
"${PDFTOTEXT:-pdftotext}" -h 2>&1 | grep -q -- '-table' \
    || { echo "xpdf pdftotext with -table not found at ${PDFTOTEXT:-pdftotext} (run deploy/server-setup.sh)."; exit 1; }

FIRST_DEPLOY=0
[ -f vendor/autoload.php ] || FIRST_DEPLOY=1

if [ "$FIRST_DEPLOY" -eq 0 ]; then
    log "Maintenance mode on"
    as_app "$PHP_BIN artisan down --retry=60 --refresh=15"
fi

log "Code: $BRANCH"
as_app "git fetch --prune origin && git checkout -q '$BRANCH' && git reset -q --hard 'origin/$BRANCH'"
as_app "git log -1 --format='%h %s (%cr)'"

log "PHP dependencies"
as_app "composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress"

if grep -qE '^APP_KEY=\s*$' .env; then
    log "Generating APP_KEY (first deploy; keep it in your secrets store)"
    as_app "$PHP_BIN artisan key:generate --force"
fi

log "Frontend build"
as_app "npm ci --no-audit --no-fund --loglevel=error && npm run build"

log "Database migrations"
as_app "$PHP_BIN artisan migrate --force"
as_app "$PHP_BIN artisan db:seed --class=PmsProviderSeeder --force"

log "Caches"
as_app "$PHP_BIN artisan optimize:clear >/dev/null && $PHP_BIN artisan optimize"

log "Restarting workers"
as_app "$PHP_BIN artisan queue:restart"
systemctl reload "$PHP_FPM"
supervisorctl status 'fgre-worker:*' || true

log "Maintenance mode off"
as_app "$PHP_BIN artisan up"
trap - ERR

APP_URL="$(grep -E '^APP_URL=' .env | cut -d= -f2-)"
if curl -fsS -o /dev/null --max-time 15 "${APP_URL}/login"; then
    log "Deployed. ${APP_URL} is up."
else
    echo "Deployed, but ${APP_URL}/login did not answer. Check nginx and storage/logs."
fi

if [ "$FIRST_DEPLOY" -eq 1 ]; then
    cat <<EOF

First deploy done. Create the first hotel group and its owner (they get an invitation email):
  sudo -u $APP_USER $PHP_BIN $APP_DIR/artisan fgre:create-group "Group name" --name-ar="اسم المجموعة" \\
       --owner-email=owner@example.com --owner-name="Owner Name"
Check the reporting mailbox:
  sudo -u $APP_USER $PHP_BIN $APP_DIR/artisan fgre:mailbox-test
EOF
fi
