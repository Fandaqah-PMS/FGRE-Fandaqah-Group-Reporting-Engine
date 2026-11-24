<?php

namespace App\Ingestion\Mailbox;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OAuth access tokens for IMAP (XOAUTH2), cached until shortly before expiry.
 *
 * - microsoft: Microsoft 365 / Exchange Online, client-credentials flow (app
 *   registration with the IMAP.AccessAsApp permission, mailbox granted to the app).
 * - google: Google Workspace / Gmail, refresh-token flow (OAuth client with the
 *   https://mail.google.com/ scope).
 */
class OAuthTokenProvider
{
    public function token(string $provider): string
    {
        $settings = config("fgre.inbox.{$provider}");

        return Cache::remember("fgre:mailbox-token:{$provider}", now()->addSeconds(300), function () use ($provider, $settings) {
            $response = match ($provider) {
                'microsoft' => Http::asForm()->post(
                    "https://login.microsoftonline.com/{$this->required($settings, 'tenant_id')}/oauth2/v2.0/token",
                    [
                        'grant_type' => 'client_credentials',
                        'client_id' => $this->required($settings, 'client_id'),
                        'client_secret' => $this->required($settings, 'client_secret'),
                        'scope' => 'https://outlook.office365.com/.default',
                    ],
                ),
                'google' => Http::asForm()->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->required($settings, 'client_id'),
                    'client_secret' => $this->required($settings, 'client_secret'),
                    'refresh_token' => $this->required($settings, 'refresh_token'),
                ]),
                default => throw new RuntimeException("Unknown mailbox OAuth provider [{$provider}]."),
            };

            if ($response->failed() || ! $response->json('access_token')) {
                // Never log the response body: it can echo credentials.
                throw new RuntimeException("Could not get a {$provider} mailbox token (HTTP {$response->status()}: {$response->json('error')}).");
            }

            return $response->json('access_token');
        });
    }

    /** @param array<string, mixed> $settings */
    private function required(array $settings, string $key): string
    {
        return $settings[$key] ?? throw new RuntimeException("Mailbox setting [{$key}] is not configured.");
    }
}
