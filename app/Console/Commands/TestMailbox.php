<?php

namespace App\Console\Commands;

use App\Ingestion\Mailbox\MailboxConnector;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('fgre:mailbox-test')]
#[Description('Check the reporting mailbox settings: connect, log in and count waiting messages')]
class TestMailbox extends Command
{
    public function handle(MailboxConnector $mailbox): int
    {
        $config = config('fgre.inbox');
        $this->components->twoColumnDetail('Auth', $config['auth']);
        $this->components->twoColumnDetail('Mailbox', (string) $config['username']);

        try {
            $client = $mailbox->connect();
            $folder = $client->getFolderByPath($config['folder']);
            if ($folder === null) {
                $this->components->error("Connected, but folder [{$config['folder']}] does not exist.");

                return self::FAILURE;
            }

            $count = $folder->query()->whereAll()->leaveUnread()->setFetchBody(false)->count();
            $client->disconnect();
        } catch (Throwable $e) {
            $this->components->error('Could not connect: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Connected. {$count} message(s) waiting in [{$config['folder']}].");

        return self::SUCCESS;
    }
}
