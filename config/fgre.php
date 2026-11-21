<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Report ingestion mailbox
    |--------------------------------------------------------------------------
    |
    | PMS systems email their scheduled reports (PDF) to one dedicated mailbox.
    | The scheduler polls it every `poll_minutes`. IMAP credentials live in
    | config/imap.php (IMAP_* env vars).
    |
    */

    'inbox' => [
        'enabled' => (bool) env('FGRE_INBOX_ENABLED', false),
        'poll_minutes' => (int) env('FGRE_INBOX_POLL_MINUTES', 5),
        'account' => env('IMAP_DEFAULT_ACCOUNT', 'default'),
        'folder' => env('FGRE_INBOX_FOLDER', 'INBOX'),
        'processed_folder' => env('FGRE_INBOX_PROCESSED_FOLDER', 'FGRE-Processed'),
        'max_attachment_bytes' => 25 * 1024 * 1024,
        'accepted_mime_types' => ['application/pdf'],
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
