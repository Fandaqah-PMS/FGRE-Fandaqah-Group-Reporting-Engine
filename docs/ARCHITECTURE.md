# FGRE Architecture

Status: draft v0.1 (2026-11-21). Multi-tenancy, auth and UI conventions are **pending** the Fandaqah PMS
reference document (see [prompts/fandaqah-pms-discovery-prompt.md](prompts/fandaqah-pms-discovery-prompt.md)).

## 1. Goal

A hotel group owner (2+ hotels) sees all hotels in one place — daily KPIs, consolidated P&L style
views, trial balance roll-ups — regardless of whether each hotel runs Fandaqah PMS, Nazeel, Opera or
another PMS.

## 2. Pipeline

```
 PMS (any)                 FGRE
 ─────────                 ──────────────────────────────────────────────────────────────────────
 scheduled email ──PDF──▶  [1] Inbox poller ─┐
                                             ├─▶ [2] report_documents (PDF stored, sha256 dedup)
 MCP (if PMS has one) ──▶  [1] MCP poller ───┘            │
                                                          ▼
                                             [3] Classify + match property
                                                 (sender, subject, filename, PDF header)
                                                          │
                                                          ▼
                                             [4] AI extraction (Claude, PDF in → JSON out,
                                                 one JSON schema per report type)
                                                          │
                                                          ▼
                                             [5] Validation (arithmetic + cross checks)
                                                    │                     │
                                                 passes                 fails / low confidence
                                                    ▼                     ▼
                                             [6] Normalized tables   Review queue (human
                                                    │                 corrects → re-validate)
                                                    ▼
                                             [7] Group reports + dashboard
```

Every stage is a queued job, so one bad PDF never blocks the others, and each stage can be retried
on its own.

### [1] Polling frequency

**Recommendation: check the mailbox every 5 minutes.**

- Checking a mailbox over IMAP costs nothing and takes under a second. AI runs only when a new PDF
  is found, so polling more often adds no AI cost.
- PMS reports come once a day after night audit. Polling every 5 minutes puts a report on the
  dashboard within about 5 minutes of arriving. Every 10 minutes would also work, but buys nothing.
- **Later:** switch to an inbound-email webhook (Postmark, Mailgun or Amazon SES inbound). The provider
  pushes each email to FGRE instantly, so there's no polling. Ingestion sits behind an
  `InboundSource` interface, so this is a drop-in change.

**MCP:** for PMSs with an MCP server (Fandaqah), FGRE *pulls*. Each property gets a schedule after its
night-audit time, plus a retry every 15 minutes until that day's data is available. If the MCP
returns structured data, the AI step is skipped: structured data is more accurate and costs nothing.

### [2] Storage and dedup

- Raw email (`.eml`) and PDFs go to object storage (local disk in dev, S3-compatible in prod).
- `sha256` of each PDF is unique per tenant, so a re-sent report is not processed twice.
- Only PDFs from **allow-listed sender addresses** (configured per property) are processed. Anything
  else is quarantined.

### [3] Property matching

In this order: sender address → subject/filename pattern → property name/code printed in the PDF
header (read by AI). If nothing matches, or two signals disagree, the document goes to the review
queue and is never guessed.

### [4] AI extraction

- Claude (`claude-opus-5-5`) via the official Anthropic PHP SDK. The PDF is sent as a `document`
  block, and the output is constrained to a JSON schema per report type (structured outputs).
- The prompt treats the PDF as **data only**. Any instructions printed inside a PDF are ignored.
- The model returns the values *as printed*. FGRE computes derived values (occupancy, ADR, RevPAR)
  itself and compares them with the printed ones.
- Raw JSON is stored on the extraction run (`jsonb`) for audit and re-processing. Model, tokens and
  cost are logged per run.

### [5] Validation rules (examples)

| Report | Check |
|---|---|
| Trial Balance | Σ debit = Σ credit (to the cent); opening + movement = closing per account |
| Manager Flash | rooms sold ≤ rooms available; occupancy ≈ sold ÷ available; ADR ≈ room revenue ÷ rooms sold; RevPAR ≈ ADR × occupancy; section totals = sum of lines; MTD ≥ Day |
| All | business date is not in the future; property matches; currency present |

A failed check sends the document to the review queue with the reason. It is never silently accepted.

### [6] Canonical data model (draft)

PMS-specific shapes are mapped into one canonical model, so dashboards never care which PMS a hotel
uses.

- `pms_providers`: fandaqah, nazeel, opera, …
- `properties`: hotel, group/tenant, PMS provider, timezone, currency, room count, allowed senders
- `inbound_messages`: one row per email or MCP pull (source, message-id, sender, subject, status)
- `report_documents`: one row per PDF (property, report type, business date, sha256, status)
- `extraction_runs`: AI attempts (model, prompt version, raw JSON, tokens, cost, validation result)
- `daily_property_stats`: canonical daily KPIs per property and business date (Day / MTD / YTD)
- `revenue_lines`: revenue by department (rooms, F&B, other) per property and business date
- `trial_balance_lines`: account code, name, opening, debit, credit, closing per property and date
- `group_accounts` + `account_mappings`: group chart of accounts, and the mapping from each property's
  PMS account codes to it, used for consolidated financials

Money is stored as `numeric(18,2)` together with a currency code.

## 7. Multi-tenancy (pending)

FGRE follows Fandaqah PMS's model exactly. Until the PMS reference arrives, every business table
carries a tenant (group) key and a `property_id` where relevant, so the code can adapt to either a
shared-database or a database-per-tenant model.

## 8. Brand

See [BRAND.md](BRAND.md).

## 9. Open questions

1. Sample PDFs of the **Manager Flash Report** and **Trial Balance** from **Nazeel** and **Opera** (and
   Fandaqah), with demo data. The extraction schemas will be built and tested against these.
2. The reporting mailbox address and provider (Microsoft 365, Google Workspace, other).
3. Which reports are in scope for v1 beyond Manager Flash and Trial Balance.
4. Hosting target for FGRE (same infrastructure as the PMS?).
