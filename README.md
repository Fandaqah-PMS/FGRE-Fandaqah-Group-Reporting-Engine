# FGRE — Fandaqah Group Reporting Engine

Centralized group reporting for hotel chains (2+ properties), independent of the PMS each hotel runs
(Fandaqah, Nazeel, Opera, …).

PMS reports (Manager Flash, Trial Balance, …) arrive as PDF by email or via MCP, are read and
normalized by AI into structured data, stored in PostgreSQL, and presented as consolidated group
reports and dashboards for owners.

- Stack: Laravel (PHP) + PostgreSQL
- Branching: `main` (production) ← `staging` ← `feature/*`
