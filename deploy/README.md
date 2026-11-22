# FGRE deployment guide (for DevOps)

FGRE is a Laravel 13 / PHP 8.4 web app with a PostgreSQL database, a queue worker and a
scheduler. It reads hotel report PDFs on the server with xpdf `pdftotext`: no AI, no external
API. One Ubuntu server is enough for a start.

## What the server needs

| Component | Version | Why |
|---|---|---|
| Ubuntu | 24.04 LTS | scripts are written for it |
| nginx | distro | web server |
| PHP-FPM + CLI | 8.4, extensions `pgsql intl mbstring bcmath xml curl zip` | the app (`intl` is required: Arabic text in PDFs) |
| PostgreSQL | 16+ (setup installs 18) | all data, sessions, cache, queue |
| Node.js | 22 LTS | builds the frontend during each deploy |
| Composer | 2 | PHP dependencies |
| **xpdf `pdftotext`** | 4.05+ from xpdfreader.com | reads report PDFs. **Not** `poppler-utils`: the Poppler build has no `-table` mode and reports will fail |
| supervisor | distro | keeps 2 queue workers running |
| cron | distro | Laravel scheduler (every minute) + nightly backup |

Sizing: 2 vCPU, 4 GB RAM, 40 GB disk is plenty for tens of hotels. Outbound access needed to
the reporting mailbox (IMAP 993 or Microsoft / Google APIs) and to the SMTP server.
Inbound: 80 / 443 only.

## Files in this folder

| File | Use |
|---|---|
| `server-setup.sh` | one-time setup of a fresh server (packages, database, nginx, worker, cron, clone) |
| `deploy.sh` | every release: pull, install, build, migrate, cache, restart workers |
| `production.env.example` | the `.env` template: copy to `/var/www/fgre/.env` and fill in |
| `nginx/fgre.conf` | nginx site (installed by setup) |
| `supervisor/fgre-worker.conf` | queue workers (installed by setup) |
| `cron/fgre` | scheduler + nightly backup (installed by setup) |

## First installation

```bash
# 1. On the server, as root (clones the repo to /var/www/fgre):
curl -fsSLO https://raw.githubusercontent.com/fandaqah/FGRE-Fandaqah-Group-Reporting-Engine/main/deploy/server-setup.sh
sudo DOMAIN=reports.example.com DB_PASSWORD='<strong password>' BRANCH=main bash server-setup.sh
# (private repository: clone it yourself with a deploy key first, then run
#  sudo DOMAIN=... DB_PASSWORD=... bash /var/www/fgre/deploy/server-setup.sh)

# 2. HTTPS
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot --nginx -d reports.example.com

# 3. Environment
sudo cp /var/www/fgre/deploy/production.env.example /var/www/fgre/.env
sudo nano /var/www/fgre/.env            # replace every CHANGE_ME
sudo chown www-data:www-data /var/www/fgre/.env && sudo chmod 640 /var/www/fgre/.env

# 4. First deploy (generates APP_KEY, migrates, builds)
sudo BRANCH=main bash /var/www/fgre/deploy/deploy.sh

# 5. First customer group + owner (owner receives an invitation email)
sudo -u www-data php /var/www/fgre/artisan fgre:create-group "Al Noor Hospitality Group" \
     --name-ar="مجموعة النور للضيافة" --owner-email=owner@example.com --owner-name="Owner Name"

# 6. Check the reporting mailbox
sudo -u www-data php /var/www/fgre/artisan fgre:mailbox-test
```

After step 5 the owner signs in, adds the hotels (Settings → Hotels: rooms, PMS and the email
addresses the hotels' reports come from) and invites colleagues (Settings → Users).

## Every release

```bash
sudo BRANCH=main bash /var/www/fgre/deploy/deploy.sh
```

About a minute of maintenance page. Branches: `main` = production, `staging` = staging server
(same script with `BRANCH=staging`). Never deploy `feature/*` branches.

## Values to get from Fandaqah before go-live

- **SMTP account** for outgoing email (invitations, password resets, report subscriptions):
  `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`.
- **Reporting mailbox** the PMSs send their reports to: `FGRE_MAILBOX_*`
  (provider-specific settings in [docs/MAILBOX.md](../docs/MAILBOX.md)).
- **Domain** for `APP_URL`.

## Operations

| Task | Command |
|---|---|
| Logs | `storage/logs/laravel-YYYY-MM-DD.log` (30 days), `storage/logs/worker.log` |
| Worker status | `sudo supervisorctl status fgre-worker:*` |
| Failed jobs | `sudo -u www-data php artisan queue:failed` / `queue:retry all` |
| Read a report again | `sudo -u www-data php artisan fgre:reprocess {document id}` |
| Poll the mailbox now | `sudo -u www-data php artisan fgre:poll-inbox` |
| Scheduled tasks | `sudo -u www-data php artisan schedule:list` |
| Maintenance on / off | `sudo -u www-data php artisan down` / `up` |

**Backups** (cron, 02:30 nightly, 14 days in `/var/backups/fgre`): PostgreSQL dump + the
received report files (`storage/app/private/reports`). Copy `/var/backups/fgre` off the server
(S3, another host). Also keep the `.env` (above all `APP_KEY`) in your secrets store.

Restore: `sudo -u postgres pg_restore --clean -d fgre fgre-YYYY-MM-DD.dump`, then untar the
reports archive into `storage/app/private`.

**Security**: `.env` is mode 640 and never committed; nginx refuses dotfiles; sessions are
encrypted and HTTPS-only (`SESSION_SECURE_COOKIE=true`); `APP_DEBUG=false` always in production.
Guest names from the reports are stored only in PostgreSQL and the report files on this server.
