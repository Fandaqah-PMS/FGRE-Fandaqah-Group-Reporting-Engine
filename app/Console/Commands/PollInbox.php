<?php

namespace App\Console\Commands;

use App\Ingestion\ImapInboxPoller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fgre:poll-inbox {--limit=50 : Maximum messages to handle in one run}')]
#[Description('Fetch new report emails from the reporting mailbox and queue their PDFs')]
class PollInbox extends Command
{
    public function handle(ImapInboxPoller $poller): int
    {
        if (! config('fgre.inbox.enabled')) {
            $this->components->warn('Inbox polling is disabled (FGRE_INBOX_ENABLED=false).');

            return self::SUCCESS;
        }

        $stats = $poller->poll((int) $this->option('limit'));

        $this->components->info(sprintf(
            'Messages: %d, new documents: %d, ignored: %d',
            $stats['messages'], $stats['documents'], $stats['ignored'],
        ));

        return self::SUCCESS;
    }
}
