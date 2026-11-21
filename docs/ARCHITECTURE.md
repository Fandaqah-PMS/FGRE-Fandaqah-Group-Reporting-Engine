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
| D5 | **KPIs are computed by FGRE from parts**, with the Fandaqah X-D13 definitions, for every PMS. Group and multi-day ratios are recomputed from summed parts, never averaged. Printed KPIs are kept for reference only. | Hotels on different PMSs compare like for like. |
| D6 | **Tenancy: hotel group → properties.** `group_id` on every business table, enforced by `BelongsToCurrentGroup` + `GroupScope` (the PMS's `BelongsToCurrentTeam` pattern, one level up). Jobs pass the group explicitly. | The PMS has no group entity (a Team is one hotel); FGRE's customer is the group. |
| D7 | **Permissions:** lowercase slugs with spaces in one config file, one Gate per permission (PMS convention). Fixed roles: owner, finance, operations, viewer. Unlike the PMS, a role only counts in its own group. Users can be limited to some hotels. | Groups are separate customers; no cross-group leakage. |
| D8 | **FGRE has its own login.** "Sign in with Fandaqah" (Passport OAuth, already in the PMS) can be added later. | Nazeel / Opera hotels' users are not PMS users. |
| D9 | **Provisional days are stored and flagged**, then replaced by the final report. | Fandaqah marks open days; dashboards show them as provisional. |
| D10 | Money: `numeric(18,2)` + currency; KPI math in bcmath, rounded half away from zero once. | Same rule as the PMS (X-D08). |

## 4. Report types

| Type | Examples | Stored in |
|---|---|---|
| `manager_flash` | Fandaqah EOD-016, Opera Manager Report | `daily_property_stats` (Day / MTD / YTD × this year / last year), `revenue_lines` |
| `trial_balance` (general ledger) | Fandaqah FST-001 / FAC-001 | `trial_balance_lines` (standard + local account no., signed balances; may cover a period) |
| `front_office_trial_balance` | Opera trial_balance, Fandaqah FIN-040 | `ledger_balances` (guest / AR / deposit / package), `transaction_code_lines` |

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

## 7. Open questions

1. How should Fandaqah trial balances reach FGRE? (a) the PMS emails FST-001 / FIN-040 after Night
   Audit, like the flash; (b) a token-protected JSON endpoint (recommended: no AI, exact);
   (c) manual upload in FGRE.
2. Could the PMS add the hotel id (`teams.id`) to the flash subject and file name? It would make
   matching exact.
3. Frontend stack for the dashboard (to be decided).
4. Nazeel and Opera sample PDFs (pending).
