# Prompt for the Fandaqah PMS codebase

> Copy everything below the line and run it inside the **Fandaqah PMS** repository (with Claude Code or
> any AI assistant that can read that codebase). Send the resulting Markdown document back to the FGRE
> team and save it as `docs/fandaqah-pms-reference.md` in the FGRE repo.

---

You are documenting the Fandaqah PMS codebase for a sister project: **FGRE (Fandaqah Group Reporting
Engine)**. FGRE is a new Laravel + PostgreSQL application that has to reuse Fandaqah PMS's
multi-tenancy model, authorization rules, coding conventions and UI style, so a developer who has never
seen the PMS can build FGRE to match it.

Read the codebase (do not guess). For every answer, cite the file path(s) it comes from and include
short code excerpts where they show a pattern. Where something does not exist, say "not present". Where
there is more than one way it's done, describe the dominant one and list the exceptions.

Produce one Markdown document with these sections:

## 1. Stack and versions
- PHP and Laravel versions (from `composer.json` / `composer.lock`), and every first-party package
  version that matters (Sanctum, Passport, Horizon, Octane, Livewire, Inertia, Filament, etc.).
- Frontend stack: Blade / Livewire / Inertia + Vue or React / SPA. CSS framework and version (Tailwind?),
  UI component library, icon set, chart library, build tool (Vite) and Node version.
- Database engine and version, cache/queue/session drivers (Redis? database?), file storage (S3?).
- Hosting and deployment: Docker, Forge, Vapor, Kubernetes, CI/CD pipeline files. Environments that
  exist (local, staging, production).

## 2. Multi-tenancy (most important)
- Which approach and package: `stancl/tenancy`, `spatie/laravel-multitenancy`, `tenancy/tenancy`, or a
  custom implementation. Include the config file(s) in full.
- Isolation model: single database with a `tenant_id` column, schema-per-tenant (PostgreSQL schemas),
  or database-per-tenant. Which tables are central (landlord) and which are tenant tables.
- How the current tenant is identified: subdomain, custom domain, path, header, or logged-in user. Show
  the middleware / tenant finder.
- **Hierarchy:** how a hotel group with several hotels is modeled. Is a tenant a company/group, a
  single property, or something else? Name the models and relations (e.g. Tenant → Organization →
  Property → Outlet). Where do property ID, property code, and property name live?
- How tenant context is carried into queued jobs, scheduled commands, events, cache keys, file storage
  paths, and logs.
- How tenants are created (onboarding flow), how tenant migrations and seeders run, and how a tenant is
  suspended or deleted.
- Base model, traits or global scopes that enforce tenant isolation (e.g. `BelongsToTenant`). Show them.

## 3. Users, authentication and authorization
- User model(s) and where users live (central vs tenant). Can one user access several properties or
  several tenants? How is "current property" chosen and stored?
- Auth: guards, Sanctum / Passport / session, SSO (Microsoft, Google, SAML), 2FA, password rules.
- Roles and permissions: package (`spatie/laravel-permission`?) or custom. List **every role** and the
  **permission naming convention**, with the seeder that creates them. How permissions are scoped per
  property. How policies and gates are organized.
- Is there an "owner" or "group-level" role that can see all properties? What can it see?
- Could FGRE share login with the PMS (SSO, shared users table, OAuth/Passport client, signed
  token)? Describe whatever already exists that would make that possible.

## 4. Coding conventions and rules
- Folder structure under `app/` (Actions, Services, Repositories, DTOs, Data objects, Enums, etc.).
  Which pattern is used for business logic? Show one representative feature end to end (route →
  controller → request validation → action/service → model → resource/response).
- Naming conventions (tables, columns, models, routes, permissions, events, jobs).
- Primary keys: auto-increment, UUID, or ULID? Soft deletes? Timestamps conventions?
- Money and numbers: how amounts are stored (decimal precision? integer minor units?), currency
  handling, VAT/tax handling, rounding rules.
- Dates: application timezone, how a property's timezone is stored, and how the **business date** and
  **night audit** work (when does the business date roll, and who triggers it?).
- Localization: Arabic/English, RTL handling, translation file layout, Hijri dates if any.
- API conventions: response envelope, error format, pagination, versioning, rate limiting.
- Audit logging / activity log package, if any.
- Static analysis and formatting: Pint config, Larastan/PHPStan level, Rector, ESLint/Prettier.
- Tests: Pest or PHPUnit, how tests set up tenancy, factories conventions.
- Any `CLAUDE.md`, `CONTRIBUTING.md`, ADRs or rules documents. Quote their key rules.

## 5. Reports FGRE will ingest
For each of these reports — **Manager Flash Report** and **Trial Balance** (and list any other
financial or KPI reports the PMS can produce, e.g. Daily Revenue, Occupancy Forecast, Market Segment,
AR Aging, Cashier/Shift reports):
- Where it is generated (classes, queries, views) and the exact list of sections, rows and columns.
- The **formula** for every KPI (Occupancy %, ADR, RevPAR, TRevPAR, ALOS, etc.): which rooms count as
  available (out of order? complimentary? house use?), which revenue counts as room revenue, tax
  inclusive or exclusive.
- Day / Month-to-date / Year-to-date / Last-year columns, and how each is computed.
- Trial Balance: chart of accounts structure, account code format, account types/groups, whether it
  follows USALI, how opening balance / debit / credit / closing balance are computed, and at what point
  in the day it is final.
- Whether the report can be produced as **structured data** (JSON/API endpoint/export) in addition to
  PDF. If yes, document the endpoint, auth, and payload. Include a sample JSON payload.
- The exact PDF layout: header fields (property name, property code, business date, print time, user),
  and file naming.

## 6. Scheduled report emails
- How scheduled report emails are configured and sent (schedule, recipients, mail driver).
- The sender address, subject line format and attachment naming. Can a property code and business date
  be included in the subject or filename? (FGRE uses these to match the PDF to the right hotel.)
- When the email is sent relative to night audit.

## 7. Fandaqah PMS MCP server
- Does the PMS expose an MCP server? If yes: URL, transport, authentication (OAuth? API key? per
  tenant?), and the **full list of tools/resources** with their input and output schemas.
- Which tools return report data (flash, trial balance, KPIs), and for which date ranges and properties.
- Rate limits and how multi-property groups are addressed.

## 8. UI / design system
- Layout (sidebar, top bar, page header), core components (tables, cards, filters, date pickers,
  modals), and where they live. Light/dark mode support.
- How the Fandaqah brand (colors, fonts El Messiri / Montserrat / Almarai, logo) is configured —
  include the Tailwind config / CSS variables with **exact hex values**.
- Dashboard and charting components, if any.

## 9. Appendix
- The full `config/tenancy.php` (or equivalent), the tenant-identification middleware, the base
  tenant-aware model/trait, the roles & permissions seeder, and one complete example feature
  (controller + request + action/service + resource + test).
- A sample Manager Flash Report PDF and a sample Trial Balance PDF (attach them as files, with
  test/demo data only — no real guest data).

Keep the document factual. Do not include secrets, `.env` values, credentials or real guest/personal
data.
