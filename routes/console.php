<?php

use Illuminate\Support\Facades\Schedule;

/*
| Report ingestion. Requires the scheduler (`php artisan schedule:work` locally,
| cron calling `schedule:run` every minute in production) and a queue worker.
*/

// Mailbox: every 5 minutes by default. Polling is cheap; AI only runs for new files.
$every = max(1, (int) config('fgre.inbox.poll_minutes', 5));

Schedule::command('fgre:poll-inbox')
    ->cron("*/{$every} * * * *")
    ->withoutOverlapping(15)
    ->when(fn () => (bool) config('fgre.inbox.enabled'));

// MCP: hotels whose PMS has an MCP server. Already-final days are skipped, so this is cheap.
Schedule::command('fgre:poll-mcp')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30);
