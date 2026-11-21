<?php

namespace Tests\Feature;

use App\Ingestion\Mailbox\MailboxConnector;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class MailboxConnectorTest extends TestCase
{
    public function test_password_mailbox_uses_plain_login(): void
    {
        config(['fgre.inbox.auth' => 'password', 'fgre.inbox.host' => 'mail.example.com',
            'fgre.inbox.username' => 'reports@example.com', 'fgre.inbox.password' => 'secret']);

        $config = app(MailboxConnector::class)->clientConfig();

        $this->assertSame('mail.example.com', $config['host']);
        $this->assertSame('secret', $config['password']);
        $this->assertNull($config['authentication']);
    }

    public function test_microsoft_mailbox_gets_an_oauth_token_and_default_host(): void
    {
        Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'ms-token', 'expires_in' => 3599])]);
        config(['fgre.inbox.auth' => 'microsoft', 'fgre.inbox.host' => null, 'fgre.inbox.username' => 'reports@hotel.com',
            'fgre.inbox.microsoft' => ['tenant_id' => 't', 'client_id' => 'c', 'client_secret' => 's']]);

        $config = app(MailboxConnector::class)->clientConfig();

        $this->assertSame('outlook.office365.com', $config['host']);
        $this->assertSame('ms-token', $config['password']);
        $this->assertSame('oauth', $config['authentication']);
        Http::assertSent(fn ($request) => $request['scope'] === 'https://outlook.office365.com/.default'
            && $request['grant_type'] === 'client_credentials');
    }

    public function test_google_mailbox_uses_the_refresh_token(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'g-token'])]);
        config(['fgre.inbox.auth' => 'google', 'fgre.inbox.host' => null, 'fgre.inbox.username' => 'reports@hotel.com',
            'fgre.inbox.google' => ['client_id' => 'c', 'client_secret' => 's', 'refresh_token' => 'r']]);

        $config = app(MailboxConnector::class)->clientConfig();

        $this->assertSame('imap.gmail.com', $config['host']);
        $this->assertSame('g-token', $config['password']);
    }

    public function test_missing_mailbox_address_is_reported_clearly(): void
    {
        config(['fgre.inbox.auth' => 'password', 'fgre.inbox.host' => 'mail.example.com', 'fgre.inbox.username' => null]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('FGRE_MAILBOX_USERNAME');

        app(MailboxConnector::class)->clientConfig();
    }
}
