# FGRE: notes for AI assistants

Fandaqah Group Reporting Engine. Laravel 13, PHP 8.4, PostgreSQL. Read [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
first; Fandaqah PMS facts are in [docs/fandaqah-pms-reference.md](docs/fandaqah-pms-reference.md).

## Rules
- Branching: `main` (production) ← `staging` ← `feature/*`. Never commit directly to `main` or `staging`.
- **Tenancy:** every business table has `group_id` and its model uses `BelongsToCurrentGroup`.
  Background code (jobs, commands) has no logged-in user: query with `Model::forGroup($id)`.
  Bypass `GroupScope` only on purpose (cross-group matching, platform admin) and comment why.
- **Permissions:** add new ones to `config/fgre_permissions.php` (lowercase words with spaces) and
  to the default roles that need them (and a `categories` entry). Groups may override non-owner roles
  (`group_roles`, read via `App\Support\RolePermissions`). Check with Gates (`can:` / `Gate::allows`),
  in the current group. Record settings changes with `AuditLog::record()`.
- **KPIs:** only `App\Reports\Kpi\KpiCalculator` computes occupancy / ADR / RevPAR, from parts.
  Never average ratios; never show `printed_*` columns as KPIs.
- **No AI.** PDFs are read by layout readers (`app/Reports/Extraction/Pdf`) on text from xpdf
  `pdftotext` (`FGRE_PDFTOTEXT`). Rows are matched by printed label (English + Arabic), columns by
  printed header; never by position, never guessed. Unknown layouts fail with a clear reason.
- Dashboards and group reports read only the canonical tables, never raw reader output.
- Read data is never trusted directly: everything goes through `ExtractionValidator`; a failed
  check sends the document to review, never to a silent fix.
- New report layout: new `PdfReportReader`, registered in `PdfReportExtractor::READERS`, tested
  on a real sample. Never commit samples with guest names; use synthetic text (`tests/Fixtures/pdf-text`).
- Money: `numeric(18,2)` + currency; KPI math in bcmath. Unreleased migrations may be edited;
  once a migration reaches `staging`, add a new one instead.
- Brand: [docs/BRAND.md](docs/BRAND.md). **UI: [docs/DESIGN-SYSTEM.md](docs/DESIGN-SYSTEM.md)** — build screens only
  from `resources/js/Components/ui` (one KpiCard, UiButton, Badge, IconTile, CardHeader, EmptyState, Field);
  theme tokens only (`text-ink`, `bg-surface`, no hex / `bg-white` / `text-navy`). `DesignRulesTest` enforces it;
  new patterns go into `Components/ui` and the `/design-system` page first.

## Commands
- Tests: `php artisan test` (SQLite in memory; the Fandaqah PDF and JSON samples are real)
- Format: `vendor/bin/pint`
- Ingest a file: `php artisan fgre:ingest-file path/to/report.pdf|.json --from=sender@pms.example`
- Process the queue: `php artisan queue:work`
- Mailbox: `php artisan fgre:mailbox-test`, `php artisan fgre:poll-inbox`
- MCP: `php artisan fgre:mcp-test {id}`, `php artisan fgre:poll-mcp` (see docs/MCP.md)
- Frontend: `npm run dev` / `npm run build` (Inertia + Vue 3 pages in resources/js/Pages; UI strings in lang/ar.json)
- Demo data: `php artisan migrate:fresh --seed` with APP_ENV=local (logins in README)
- Charts: series colours only from resources/js/lib/palette.js (validated); text never in series colours
- Re-run a document: `php artisan fgre:reprocess {id}`
