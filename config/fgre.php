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
        'prompt_version' => '2026-11-21.1',
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

    'validation' => [
        'money_tolerance' => 0.05,      // absolute, in report currency
        'percent_tolerance' => 0.15,    // absolute, in percentage points
    ],

];
