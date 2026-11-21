<?php

use Illuminate\Support\Facades\Schedule;

/*
| Report ingestion: poll the reporting mailbox (default every 5 minutes).
| Polling is cheap; AI only runs when a new PDF arrives.
| Requires the scheduler (`php artisan schedule:work` locally, cron in production)
| and a queue worker (`php artisan queue:work`).
*/
$every = max(1, (int) config('fgre.inbox.poll_minutes', 5));

Schedule::command('fgre:poll-inbox')
    ->cron("*/{$every} * * * *")
    ->withoutOverlapping(15)
    ->when(fn () => (bool) config('fgre.inbox.enabled'));
