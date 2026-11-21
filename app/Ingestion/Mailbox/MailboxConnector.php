<?php

namespace App\Ingestion\Mailbox;

use InvalidArgumentException;
use Webklex\IMAP\Facades\Client as ImapClient;
use Webklex\PHPIMAP\Client;

/**
 * Builds a connected IMAP client for the reporting mailbox from config('fgre.inbox').
 * FGRE_MAILBOX_AUTH: password (any IMAP server) | microsoft | google.
 */
class MailboxConnector
{
    private const DEFAULT_HOSTS = [
        'microsoft' => 'outlook.office365.com',
        'google' => 'imap.gmail.com',
    ];

    public function __construct(private readonly OAuthTokenProvider $tokens) {}

    public function connect(): Client
    {
        $client = ImapClient::make($this->clientConfig());
        $client->connect();

        return $client;
    }

    /** @return array<string, mixed> */
    public function clientConfig(): array
    {
        $inbox = config('fgre.inbox');
        $auth = $inbox['auth'];

        if (! in_array($auth, ['password', 'microsoft', 'google'], true)) {
            throw new InvalidArgumentException("FGRE_MAILBOX_AUTH must be password, microsoft or google; got [{$auth}].");
        }

        $host = $inbox['host'] ?: (self::DEFAULT_HOSTS[$auth] ?? null);
        if (! $host || ! $inbox['username']) {
            throw new InvalidArgumentException('Set FGRE_MAILBOX_HOST and FGRE_MAILBOX_USERNAME (the reporting email address).');
        }

        return [
            'host' => $host,
            'port' => (int) $inbox['port'],
            'encryption' => $inbox['encryption'],
            'validate_cert' => (bool) $inbox['validate_cert'],
            'username' => $inbox['username'],
            'password' => $auth === 'password' ? (string) $inbox['password'] : $this->tokens->token($auth),
            'protocol' => 'imap',
            'authentication' => $auth === 'password' ? null : 'oauth',
        ];
    }
}
