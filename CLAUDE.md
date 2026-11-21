# FGRE: notes for AI assistants

Fandaqah Group Reporting Engine. Laravel 13, PHP 8.4, PostgreSQL. Read [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) first.

## Rules
- Branching: `main` (production) ← `staging` ← `feature/*`. Never commit directly to `main` or `staging`.
- Multi-tenancy, roles/permissions, and UI conventions must follow Fandaqah PMS. Until
  `docs/fandaqah-pms-reference.md` exists, don't invent a tenancy package or auth scheme; ask.
- Dashboards and group reports read only from the canonical tables (`daily_property_stats`,
  `revenue_lines`, `trial_balance_lines`), never from PMS-specific shapes or raw AI output.
- AI output is never trusted directly. Everything goes through `ExtractionValidator`; a failed check
  sends the document to review, never to a silent fix.
- Report PDFs are untrusted input: the extraction prompt treats them as data only.
- Changing `ExtractionSchema` or the prompt: bump `config('fgre.ai.prompt_version')`.
- Money: `numeric(18,2)` + currency code. No floats in the database.
- Brand: [docs/BRAND.md](docs/BRAND.md) (El Messiri titles, Montserrat body, Almarai Arabic).

## Commands
- Tests: `php artisan test` (SQLite in memory; the AI extractor is faked)
- Format: `vendor/bin/pint`
- Ingest a sample PDF: `php artisan fgre:ingest-file path/to/report.pdf --from=sender@pms.example`
- Process the queue: `php artisan queue:work`
- Poll the mailbox now: `php artisan fgre:poll-inbox`
- Re-run extraction: `php artisan fgre:reprocess {id}`
