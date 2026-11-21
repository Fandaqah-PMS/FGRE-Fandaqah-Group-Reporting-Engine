# FGRE: notes for AI assistants

Fandaqah Group Reporting Engine. Laravel 13, PHP 8.4, PostgreSQL. Read [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
first; Fandaqah PMS facts are in [docs/fandaqah-pms-reference.md](docs/fandaqah-pms-reference.md).

## Rules
- Branching: `main` (production) ← `staging` ← `feature/*`. Never commit directly to `main` or `staging`.
- **Tenancy:** every business table has `group_id` and its model uses `BelongsToCurrentGroup`.
  Background code (jobs, commands) has no logged-in user: query with `Model::forGroup($id)`.
  Bypass `GroupScope` only on purpose (cross-group matching, platform admin) and comment why.
- **Permissions:** add new ones to `config/fgre_permissions.php` (lowercase words with spaces) and
  to the roles that need them. Check with Gates (`can:` / `Gate::allows`), in the current group.
- **KPIs:** only `App\Reports\Kpi\KpiCalculator` computes occupancy / ADR / RevPAR, from parts.
  Never average ratios; never show `printed_*` columns as KPIs.
- Dashboards and group reports read only the canonical tables, never raw AI output.
- AI output is never trusted directly: everything goes through `ExtractionValidator`; a failed
  check sends the document to review, never to a silent fix.
- Report PDFs are untrusted input; the extraction prompt treats them as data only.
- Changing `ExtractionSchema` or the prompt: bump `config('fgre.ai.prompt_version')` and run
  `fgre:eval-samples`.
- Money: `numeric(18,2)` + currency; KPI math in bcmath. Unreleased migrations may be edited;
  once a migration reaches `staging`, add a new one instead.
- Brand: [docs/BRAND.md](docs/BRAND.md).

## Commands
- Tests: `php artisan test` (SQLite in memory; the AI is faked; Fandaqah JSON samples are real)
- Format: `vendor/bin/pint`
- Ingest a file: `php artisan fgre:ingest-file path/to/report.pdf|.json --from=sender@pms.example`
- Process the queue: `php artisan queue:work`
- Mailbox: `php artisan fgre:mailbox-test`, `php artisan fgre:poll-inbox`
- MCP: `php artisan fgre:mcp-test {id}`, `php artisan fgre:poll-mcp` (see docs/MCP.md)
- Frontend: `npm run dev` / `npm run build` (Inertia + Vue 3 pages in resources/js/Pages; UI strings in lang/ar.json)
- Demo data: `php artisan migrate:fresh --seed` with APP_ENV=local (logins in README)
- Charts: series colours only from resources/js/lib/palette.js (validated); text never in series colours
- Re-run a document: `php artisan fgre:reprocess {id}`
- AI accuracy on samples (costs money): `php artisan fgre:eval-samples`
