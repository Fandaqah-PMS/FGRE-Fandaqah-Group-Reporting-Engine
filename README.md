# FGRE — Fandaqah Group Reporting Engine

Centralized group reporting for hotel chains (2+ properties), whichever PMS each hotel runs
(Fandaqah, Nazeel, Opera, …).

PMS reports (Manager Flash, trial balances) arrive by **email** (any mailbox) or from a PMS
**MCP server**. Structured data is imported directly; PDFs are read on the server by layout readers (no AI). Every figure is
cross-checked before it is stored, and the group dashboard shows all hotels in one place, in
Arabic and English.

- Stack: Laravel 13 (PHP 8.4), PostgreSQL, Inertia + Vue 3 + Tailwind 4
- Branching: `main` (production) ← `staging` ← `feature/*`
- Docs: [Architecture](docs/ARCHITECTURE.md) · [Mailbox setup](docs/MAILBOX.md) · [MCP setup](docs/MCP.md) ·
  [Brand](docs/BRAND.md) · [Fandaqah PMS reference](docs/fandaqah-pms-reference.md)

## Run locally

Requirements: PHP 8.3+ (`pdo_pgsql`, `pgsql`, `mbstring`, `intl`, `zip`, `curl`, `openssl`,
`fileinfo`, `bcmath`), Composer 2, Node 20+, PostgreSQL 15+.

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
# create the database (createdb fgre) and set DB_* in .env
php artisan migrate --seed          # APP_ENV=local also seeds the demo groups
php artisan serve                   # http://127.0.0.1:8000
```

Demo logins (password `password`):

| Email | Sees |
|---|---|
| `owner@demo.example` | everything, in two groups (Al Noor Hospitality Group, Demo Hotel Group) |
| `finance@demo.example` | dashboard, financials, review queue |
| `viewer@demo.example` | dashboard for two hotels only, no financials |
| `operations@demo.example` | invited, not signed in yet (shows "Invitation pending" in Settings → Users); no password until the invitation link is used |

Al Noor Hospitality Group is generated demo data (5 hotels on 3 PMSs, Jan 2025 – Nov 2026). Demo
Hotel Group holds the real Fandaqah sample reports from `docs/samples/`.

To process real reports, also run `php artisan queue:work` and `php artisan schedule:work`, and set
the xpdf `pdftotext` path (`FGRE_PDFTOTEXT`) plus the mailbox settings in `.env`.

## Useful commands

| Command | What it does |
|---|---|
| `php artisan test` | Test suite (Fandaqah PDF and JSON samples are real) |
| `php artisan fgre:ingest-file path.pdf\|.json --from=sender@pms` | Ingest a report file by hand |
| `php artisan fgre:mailbox-test` | Check the mailbox settings |
| `php artisan fgre:mcp-test {id}` | List a PMS MCP server's tools |
| `php artisan fgre:reprocess {id}` | Run a document through the pipeline again |
