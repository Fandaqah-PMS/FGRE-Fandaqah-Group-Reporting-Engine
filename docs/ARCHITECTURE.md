# FGRE Architecture

Status: v0.2 (2026-11-21). Built on the Fandaqah PMS reference ([fandaqah-pms-reference.md](fandaqah-pms-reference.md)).

## 1. Goal

A hotel group owner (2+ hotels) sees all hotels in one place (daily KPIs, last-year comparison,
trial balances), whichever PMS each hotel runs: Fandaqah, Nazeel, Opera or another.

## 2. Pipeline

```
 PMS email (PDF)  ─┐                          ┌─ PDF  → ClaudeReportExtractor (AI)
 PMS API / MCP (JSON) ─┼─▶ report_documents ──▶ ExtractorResolver ─┤
 Manual upload ───┘   (stored, sha256 dedup)  └─ JSON → FandaqahReportsPlusExtractor (no AI)
                                                          │
                                       canonical extraction shape (ExtractionSchema)
                                                          │
                         PropertyMatcher (sender allow-list + printed name/code + subject)
                         ExtractionValidator (arithmetic checks, subject/file-name date)
                                     │ pass                     │ fail
                                     ▼                          ▼
                     canonical tables (ReportDataWriter)    review queue
                                     │
                     KpiCalculator / GroupKpiService → dashboards
```

Every source produces the same canonical shape, so matching, checks and storage are shared. A
new source (Nazeel API, Opera export, Fandaqah MCP) only needs a new extractor.

## 3. Decisions

| # | Decision | Why |
|---|---|---|
| D1 | **Poll the mailbox every 5 minutes** over IMAP. | Polling is free; AI runs only for new files. Fandaqah emails the flash right after Night Audit, so 5 minutes is near real time. |
| D2 | **Any mailbox**: `password` (any IMAP), `microsoft` (Microsoft 365 OAuth), `google` (OAuth). | Microsoft and Google no longer allow password IMAP. See [MAILBOX.md](MAILBOX.md). |
| D3 | **Structured data skips the AI.** Fandaqah Reports Plus JSON is mapped directly. | Exact, free, instant. The AI is for PMSs that only send PDFs. |
| D4 | **AI output is never trusted directly.** Printed ratios must match one of the standard definitions; trial balances must balance; the date must match the email subject / file name. Failures go to review. | Financial data: a misread digit must not reach an owner's dashboard. |
| D5 | **KPIs follow USALI 11th edition**, computed by FGRE from parts for every PMS: rooms sold = occupied − complimentary − house use; out-of-order rooms stay in rooms available; Occupancy = rooms sold ÷ rooms available, ADR = room revenue ÷ rooms sold, RevPAR / TRevPAR = room / total revenue ÷ rooms available. Occupancy excluding out-of-order is a secondary figure. Group and multi-day ratios are recomputed from summed parts, never averaged. | Industry standard; hotels on different PMSs compare like for like. |
| D6 | **Tenancy: hotel group → properties.** `group_id` on every business table, enforced by `BelongsToCurrentGroup` + `GroupScope` (the PMS's `BelongsToCurrentTeam` pattern, one level up). Jobs pass the group explicitly. | The PMS has no group entity (a Team is one hotel); FGRE's customer is the group. |
| D7 | **Permissions:** lowercase slugs with spaces in one config file, one Gate per permission (PMS convention). Fixed roles: owner, finance, operations, viewer. Unlike the PMS, a role only counts in its own group. Users can be limited to some hotels. | Groups are separate customers; no cross-group leakage. |
| D8 | **FGRE has its own login.** "Sign in with Fandaqah" (Passport OAuth, already in the PMS) can be added later. | Nazeel / Opera hotels' users are not PMS users. |
| D9 | **Provisional days are stored and flagged**, then replaced by the final report. | Fandaqah marks open days; dashboards show them as provisional. |
| D10 | Money: `numeric(18,2)` + currency; KPI math in bcmath, rounded half away from zero once. | Same rule as the PMS (X-D08). |
| D11 | **Email is the universal channel**: FGRE never needs a change or a token in any PMS. **MCP is optional**: any PMS with an MCP server is read through a generic client plus per-hotel tool settings ([MCP.md](MCP.md)). | FGRE targets many PMSs (decided 2026-11-21). |
| D12 | **Frontend: Inertia + Vue 3 + Tailwind 4**, one Laravel app, Arabic (RTL, default) and English. Charts are hand-written SVG/HTML components, like the PMS dashboards. | The team already writes Vue; full control over brand and RTL. |
| D13 | **Fandaqah identity only** (identity guide + web identity concept): white sidebar with the original logo, coral active item, navy hero with the original pattern, white cards. Charts use the core palette with fixed roles: this year coral, last year grey `#889594`, revenue mix navy / coral / peach, heatmaps a coral ramp. All pairs pass the colour-blind separation check; every chart also has a legend, labels and a table view. | The product must look like Fandaqah. |
| D14 | **One hotel switch for every page**: "All hotels" (consolidated: parts summed, KPIs recomputed) or a single hotel, stored per group in the session. Every report page has a Charts / Tables switch. | Owners compare the group and drill into a hotel with one control. |

| D15 | **Every report has three views** from one row definition (`resources/js/lib/reports.js`): Group total, Side by side (report rows × hotels, hotel-set subtotals, group; best/weakest shaded), Hotel vs group (bullet bars vs group, best hotel and basis). Same toolbar everywhere: compare with Last year or Budget, pick hotels or a hotel set, export to Excel (CSV) or PDF (print). | One way to read and compare all hotels, for current and future reports. |
| D16 | **Budget to date**: monthly budgets per hotel (CSV template, row-level validation, all-or-nothing import), spread evenly over the month's days; group and set budgets count only hotels that reported (like for like). | Hospitality-standard budget comparison. |
| D17 | **Hotel health board** on the dashboard: one card per hotel with a status (Action needed / Watch / Note / On track) built from the needs-attention checks (missing / provisional reports, review queue, occupancy or ADR down vs last year, RevPAR index below group, revenue below budget, unbalanced trial balance, AR growth, guests checked out still owing, very large guest folios) with thresholds in `config/fgre.php`; **hotel ranking** by RevPAR, occupancy, ADR, growth or budget achieved. | Owners first want to know what is wrong and who leads. |
| D18 | **Email subscriptions** (`report_subscriptions`): each user picks daily (once per business date, after the chosen time, waiting for a late night audit), weekly (chosen weekday, last 7 days + MTD) or monthly (chosen day, previous month + YTD); charts (KPI tiles and bar charts drawn with email-safe tables), tables (hotels side by side + CSV) or both; all or some of their hotels; Arabic or English. `fgre:send-subscriptions` runs every 15 minutes in the group timezone; hotels and permission are re-checked at send time. | Each owner gets the group in the inbox the way they read it. |
| D19 | **Settings**: profile (name, email with password check, language, password), users (invite by email with a 7-day set-password link from its own broker, role, hotel access; owner rules: only owners give/take the owner role, nobody edits their own access, a group keeps at least one owner), roles & permissions (group overrides of the config defaults in `group_roles`, owner role fixed, editable by owners), hotels (rooms, PMS, region, sender allow-list, other names; deactivate, never delete), group (names, currency, timezone) and an append-only activity log (`audit_logs`). Forgot-password on the sign-in page. | Owners run their group without Fandaqah support, with a trail of who changed what. |

## 4. Report types

| Type | Examples | Stored in |
|---|---|---|
| `manager_flash` | Fandaqah EOD-016, Opera Manager Report | `daily_property_stats` (Day / MTD / YTD × this year / last year), `revenue_lines` |
| `trial_balance` (general ledger) | Fandaqah FST-001 / FAC-001 | `trial_balance_lines` (standard + local account no., signed balances; may cover a period) |
| `front_office_trial_balance` | Opera trial_balance, Fandaqah FIN-040 | `ledger_balances` (guest / AR / deposit / package), `transaction_code_lines` |
| `guest_trial_balance` | Fandaqah EOD-010 Guest Trial Balance | `guest_ledger_lines` (one folio per guest / company: room, stay, opening, debits, credits, closing, status), guest total in `ledger_balances` |

Fandaqah hotels share the NTMP chart of accounts, so their general-ledger trial balances
consolidate by account number directly. Other PMSs will need an account mapping table
(`group_accounts` + `account_mappings`, not built yet).

## 5. Fandaqah specifics

- Only hotels on **Night Audit v2** with the flash mail switched on receive the scheduled
  **Manager Flash** email (subject `Manager Flash – {hotel} – {Y-m-d}`, file
  `manager_flash_{Ymd}.pdf`). Emailed days are always closed.
- **Trial balances are not emailed** by the PMS. Options in §7.
- No property code is printed. FGRE matches by the hotel name (English or Arabic) and stores the PMS
  `teams.id` as `pms_property_ref`.
- No MCP server exists in the PMS repository. A token-protected Reports Plus endpoint in the PMS
  would feed FGRE JSON with no AI (the extractor already exists).

## 6. Testing with samples

- `php artisan test`: includes the real Fandaqah JSON samples (mapping, checks, KPI maths).
- `php artisan fgre:eval-samples`: sends the sample PDFs to Claude and compares the result with
  the JSON ground truth. Needs `ANTHROPIC_API_KEY`; costs a few cents per PDF.

## 7. Open items

1. Trial balances arrive by email like every other report (D11). Fandaqah PMS does not email them
   yet; hotels can schedule them once the PMS supports it, or send them by hand.
2. Optional PMS improvement: the hotel id (`teams.id`) in the flash subject and file name would make
   matching exact.
3. Nazeel and Opera sample PDFs (pending): run `fgre:eval-samples` on them when they arrive.
4. Admin screens for hotels, users, senders and MCP connections (seeded / database for now).
