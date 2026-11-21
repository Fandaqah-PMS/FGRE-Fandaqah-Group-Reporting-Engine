<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Report ingestion mailbox
    |--------------------------------------------------------------------------
    |
    | PMS systems email their scheduled reports to one dedicated mailbox, which
    | the scheduler polls every `poll_minutes`. Works with any IMAP mailbox.
    | See docs/MAILBOX.md for the settings per provider.
    |
    |   auth = password   any IMAP server (cPanel, Zoho, Yahoo, Gmail app password ...)
    |   auth = microsoft  Microsoft 365 / Outlook (OAuth, client credentials)
    |   auth = google     Google Workspace / Gmail (OAuth, refresh token)
    |
    */

    'inbox' => [
        'enabled' => (bool) env('FGRE_INBOX_ENABLED', false),
        'poll_minutes' => (int) env('FGRE_INBOX_POLL_MINUTES', 5),

        'auth' => env('FGRE_MAILBOX_AUTH', 'password'),
        'host' => env('FGRE_MAILBOX_HOST'),             // optional for microsoft / google
        'port' => (int) env('FGRE_MAILBOX_PORT', 993),
        'encryption' => env('FGRE_MAILBOX_ENCRYPTION', 'ssl'),
        'validate_cert' => (bool) env('FGRE_MAILBOX_VALIDATE_CERT', true),
        'username' => env('FGRE_MAILBOX_USERNAME'),     // the reporting email address
        'password' => env('FGRE_MAILBOX_PASSWORD'),     // auth = password only

        'microsoft' => [
            'tenant_id' => env('FGRE_MAILBOX_MS_TENANT_ID'),
            'client_id' => env('FGRE_MAILBOX_MS_CLIENT_ID'),
            'client_secret' => env('FGRE_MAILBOX_MS_CLIENT_SECRET'),
        ],

        'google' => [
            'client_id' => env('FGRE_MAILBOX_GOOGLE_CLIENT_ID'),
            'client_secret' => env('FGRE_MAILBOX_GOOGLE_CLIENT_SECRET'),
            'refresh_token' => env('FGRE_MAILBOX_GOOGLE_REFRESH_TOKEN'),
        ],

        'folder' => env('FGRE_MAILBOX_FOLDER', 'INBOX'),
        'processed_folder' => env('FGRE_MAILBOX_PROCESSED_FOLDER', 'FGRE-Processed'),
        'max_attachment_bytes' => 25 * 1024 * 1024,
        'accepted_mime_types' => ['application/pdf', 'application/json'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Document storage
    |--------------------------------------------------------------------------
    */

    'storage' => [
        'disk' => env('FGRE_STORAGE_DISK', 'local'),
        'path' => 'reports',
    ],

    /*
    |--------------------------------------------------------------------------
    | AI extraction
    |--------------------------------------------------------------------------
    |
    | Bump `prompt_version` whenever the extraction prompt or a schema changes,
    | so every extraction run records which version produced it.
    |
    */

    'ai' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('FGRE_AI_MODEL', 'claude-opus-5-5'),
        'effort' => env('FGRE_AI_EFFORT', 'high'),
        'max_tokens' => (int) env('FGRE_AI_MAX_TOKENS', 64000),
        'prompt_version' => '2026-11-22.1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation tolerances
    |--------------------------------------------------------------------------
    |
    | Printed values are compared against values FGRE recomputes. Differences
    | larger than these send the document to the review queue.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Needs attention thresholds (dashboard)
    |--------------------------------------------------------------------------
    */

    'attention' => [
        'occupancy_drop_pp' => 5,      // MTD occupancy down this many points vs last year
        'adr_drop_pct' => 5,           // MTD ADR down this % vs last year
        'revpar_index_gap' => 15,      // RevPAR index below 100 − this (vs group)
        'budget_gap_pct' => 5,         // MTD revenue below budget by this % (2× = critical)
        'ar_growth_pct' => 15,         // AR (city ledger) balance up this % in a day
        'guest_balance_large' => 50000, // one guest folio owing at least this much (report currency)
    ],

    /*
    |--------------------------------------------------------------------------
    | Email subscriptions (users choose daily / weekly / monthly, charts / tables)
    |--------------------------------------------------------------------------
    |
    | The scheduler checks every 15 minutes which subscriptions are due, in the
    | group's timezone. Each period is sent once (report_subscriptions.last_period_key).
    |
    */

    'subscriptions' => [
        'enabled' => (bool) env('FGRE_SUBSCRIPTIONS', true),
    ],

    'validation' => [
        'money_tolerance' => 0.05,      // absolute, in report currency
        'percent_tolerance' => 0.15,    // absolute, in percentage points
    ],

];
