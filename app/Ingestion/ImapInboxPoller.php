<?php

namespace App\Ingestion;

use App\Enums\InboundSource;
use App\Models\InboundMessage;
use App\Reports\IngestReportFile;
use App\Reports\PropertyMatcher;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;
use Webklex\IMAP\Facades\Client as ImapClient;
use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Message;

/**
 * Reads the reporting mailbox and ingests PDF attachments.
 *
 * Every message left in the inbox folder is handled once and then moved to the
 * processed folder; the Message-ID is also recorded, so a message is never
 * ingested twice even if the move fails.
 */
class ImapInboxPoller
{
    public function __construct(
        private readonly IngestReportFile $ingest,
        private readonly PropertyMatcher $matcher,
    ) {}

    /**
     * @return array{messages: int, documents: int, ignored: int}
     */
    public function poll(int $limit = 50): array
    {
        $stats = ['messages' => 0, 'documents' => 0, 'ignored' => 0];

        $client = ImapClient::account(config('fgre.inbox.account'));
        $client->connect();

        $folder = $client->getFolderByPath(config('fgre.inbox.folder'));
        $processedFolder = config('fgre.inbox.processed_folder');
        if ($client->getFolderByPath($processedFolder, soft_fail: true) === null) {
            $client->createFolder($processedFolder);
        }

        $messages = $folder->query()->whereAll()->leaveUnread()->limit($limit)->get();

        foreach ($messages as $message) {
            $stats['messages']++;

            try {
                $created = $this->handle($message);
                if ($created === 0) {
                    $stats['ignored']++;
                } else {
                    $stats['documents'] += $created;
                }
                $message->move($processedFolder);
            } catch (Throwable $e) {
                // Leave the message in the inbox; the next poll retries it.
                Log::error('FGRE inbox: failed to handle message', [
                    'message_id' => (string) $message->getMessageId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $client->disconnect();

        return $stats;
    }

    /** @return int number of documents created (0 = ignored) */
    private function handle(Message $message): int
    {
        $from = $message->getFrom()->first()?->mail;
        $messageId = (string) $message->getMessageId()
            ?: 'no-id:'.sha1($from.'|'.$message->getSubject().'|'.$message->getDate());

        $inbound = InboundMessage::firstOrCreate(
            ['source' => InboundSource::Email, 'external_id' => $messageId],
            [
                'from_email' => $from,
                'subject' => mb_substr((string) $message->getSubject(), 0, 1000),
                'received_at' => $message->getDate()->toDate(),
            ],
        );

        if (! $inbound->wasRecentlyCreated) {
            return 0;
        }

        // Only allow-listed senders reach the AI step (cost control and spoofing protection).
        if ($this->matcher->candidatesForSender($from)->isEmpty()) {
            $inbound->update(['status' => 'ignored', 'status_reason' => "Sender [{$from}] is not allow-listed."]);

            return 0;
        }

        $pdfs = $message->getAttachments()->filter(fn (Attachment $a) => $this->isPdf($a));
        $inbound->update(['attachment_count' => $pdfs->count()]);

        if ($pdfs->isEmpty()) {
            $inbound->update(['status' => 'ignored', 'status_reason' => 'No PDF attachment.']);

            return 0;
        }

        $created = 0;
        $problems = [];
        foreach ($pdfs as $attachment) {
            try {
                if (($this->ingest)($attachment->getContent(), $attachment->getName(), $inbound) !== null) {
                    $created++;
                }
            } catch (InvalidArgumentException $e) {
                $problems[] = $e->getMessage();
            }
        }

        $inbound->update([
            'status' => 'processed',
            'status_reason' => $problems === [] ? null : implode("\n", $problems),
        ]);

        return $created;
    }

    private function isPdf(Attachment $attachment): bool
    {
        return in_array(strtolower((string) $attachment->getMimeType()), config('fgre.inbox.accepted_mime_types'), true)
            || str_ends_with(strtolower((string) $attachment->getName()), '.pdf');
    }
}
