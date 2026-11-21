# Reading reports from a PMS MCP server

Email works with every PMS and needs nothing from the PMS vendor. If a hotel's PMS also runs an
MCP server, FGRE can read reports from it directly. That is faster, and with structured data it
needs no AI.

FGRE speaks standard MCP over Streamable HTTP (JSON-RPC: `initialize`, `tools/list`,
`tools/call`). Nothing is PMS-specific: you tell FGRE which tool returns which report.

## 1. Add the connection (one per PMS server, per group)

Table `mcp_connections` (an admin screen will come later):

| Field | Example |
|---|---|
| `group_id` | the hotel group |
| `name` | `Fandaqah MCP` |
| `url` | `https://pms.example.com/mcp` |
| `auth_type` | `bearer` (token in `Authorization: Bearer …`), `header` (custom header), or `none` |
| `auth_header` | header name when `auth_type = header`, e.g. `X-Api-Key` |
| `secret` | the token; stored encrypted |

Then list what the server offers:

```bash
php artisan fgre:mcp-test {connection-id}
```

## 2. Tell each hotel which tool returns which report

On the property: `mcp_connection_id`, `night_audit_time` (local, e.g. `04:00`) and `mcp_settings`:

```json
{
  "reports": {
    "manager_flash": {
      "tool": "run_report",
      "arguments": { "report": "manager-flash", "hotel_id": "{pms_property_ref}", "date": "{business_date}" }
    },
    "trial_balance": {
      "tool": "run_report",
      "arguments": { "report": "fin-trial-balance", "hotel_id": "{pms_property_ref}", "from": "{month_start}", "to": "{business_date}" }
    }
  }
}
```

Placeholders: `{pms_property_ref}`, `{property_code}`, `{business_date}`, `{month_start}`, `{year_start}`.

`{business_date}` is the last day closed by night audit: yesterday once the hotel's local time
passes `night_audit_time`, otherwise the day before.

## 3. What happens

`fgre:poll-mcp` runs every 15 minutes. For each hotel and report it skips days that are already
stored as final, calls the tool, and passes the result into the same pipeline as email:

| Tool returns | Handled as |
|---|---|
| `structuredContent`, or text that is Fandaqah Reports Plus JSON | imported directly, no AI |
| a PDF resource (`mimeType: application/pdf`, base64 `blob`) | read by AI, like an emailed PDF |
| other text or JSON | read by AI as text |

The hotel is known from the settings, so no sender allow-list is needed. FGRE still checks that
the hotel name printed in the result matches the hotel. Errors are saved on the connection
(`last_error`) and the next run tries again.
