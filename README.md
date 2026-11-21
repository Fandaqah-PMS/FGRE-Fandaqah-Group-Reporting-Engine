# FGRE — Fandaqah Group Reporting Engine

Centralized group reporting for hotel chains (2+ properties), independent of the PMS each hotel runs
(Fandaqah, Nazeel, Opera, …).

PMS reports (Manager Flash, Trial Balance, …) arrive as PDF by email or via MCP, are read and
normalized by AI into structured data, stored in PostgreSQL, and presented as consolidated group
reports and dashboards for owners.

- Stack: Laravel 13 (PHP 8.4) + PostgreSQL
- Branching: `main` (production) ← `staging` ← `feature/*`
- Architecture: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) · Brand: [docs/BRAND.md](docs/BRAND.md)

## Local setup

Requirements: PHP 8.3+ (with `pdo_pgsql`, `pgsql`, `mbstring`, `intl`, `zip`, `curl`, `openssl`,
`fileinfo`), Composer 2, PostgreSQL 15+.

```bash
composer install
cp .env.example .env
php artisan key:generate
# create the database (e.g. `createdb fgre`), then set DB_* in .env
php artisan migrate --seed
php artisan test
```

To process reports, set `ANTHROPIC_API_KEY` in `.env`, then:

```bash
php artisan fgre:ingest-file path/to/report.pdf --from=sender@pms.example   # or enable the mailbox:
# FGRE_INBOX_ENABLED=true + IMAP_* settings, then run the scheduler
php artisan queue:work
php artisan schedule:work
```

A property must exist with the sender in `allowed_sender_emails` before its reports are accepted.

## How a report flows

`email / MCP` → `inbound_messages` → `report_documents` (PDF stored, sha256 dedup) →
`ProcessReportDocument` job: AI extraction (`ClaudeReportExtractor`) → hotel matching
(`PropertyMatcher`) → checks (`ExtractionValidator`) → canonical tables (`ReportDataWriter`), or the
review queue if anything doesn't add up.
