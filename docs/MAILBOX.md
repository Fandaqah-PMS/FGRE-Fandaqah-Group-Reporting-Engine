# Reporting mailbox setup

FGRE reads one mailbox that PMSs email their reports to. Any mailbox works: set the address and
how to log in in `.env`, then run `php artisan fgre:mailbox-test`.

FGRE checks the mailbox every 5 minutes (`FGRE_INBOX_POLL_MINUTES`). Each handled email is moved
to the `FGRE-Processed` folder (created automatically). Emails from senders that no hotel has
allow-listed are ignored and never reach the AI.

## 1. Any IMAP mailbox (cPanel, Plesk, Zoho, Yahoo, GoDaddy, ...)

```env
FGRE_MAILBOX_AUTH=password
FGRE_MAILBOX_HOST=mail.yourdomain.com
FGRE_MAILBOX_PORT=993
FGRE_MAILBOX_ENCRYPTION=ssl
FGRE_MAILBOX_USERNAME=reports@yourdomain.com
FGRE_MAILBOX_PASSWORD=...
```

The host and port are in the mail provider's "IMAP settings" page.

**Gmail with an app password** also works this way: turn on 2-step verification, create an app
password (Google Account → Security → App passwords), and use host `imap.gmail.com`.

## 2. Microsoft 365 / Outlook (Exchange Online)

Microsoft no longer accepts passwords over IMAP, so FGRE logs in with OAuth as an app.

1. **Entra admin center → App registrations → New registration.** Name it "FGRE mailbox".
2. **API permissions → Add → APIs my organization uses → Office 365 Exchange Online →
   Application permissions → `IMAP.AccessAsApp`** → Grant admin consent.
3. **Certificates & secrets → New client secret.** Copy the value.
4. Allow the app to open the reporting mailbox (Exchange Online PowerShell, as an admin):

   ```powershell
   New-ServicePrincipal -AppId <client-id> -ObjectId <enterprise-app-object-id>
   Add-MailboxPermission -Identity reports@yourdomain.com -User <enterprise-app-object-id> -AccessRights FullAccess
   ```

```env
FGRE_MAILBOX_AUTH=microsoft
FGRE_MAILBOX_USERNAME=reports@yourdomain.com
FGRE_MAILBOX_MS_TENANT_ID=...
FGRE_MAILBOX_MS_CLIENT_ID=...
FGRE_MAILBOX_MS_CLIENT_SECRET=...
```

## 3. Google Workspace / Gmail (OAuth)

1. **Google Cloud console → APIs & Services → enable the Gmail API.**
2. **OAuth consent screen**, then **Credentials → Create OAuth client ID** (type "Desktop app").
3. Get a refresh token for the reporting mailbox with the `https://mail.google.com/` scope (for
   example with the OAuth 2.0 Playground, using your own client id and secret). Sign in **as the
   reporting mailbox**.

```env
FGRE_MAILBOX_AUTH=google
FGRE_MAILBOX_USERNAME=reports@yourdomain.com
FGRE_MAILBOX_GOOGLE_CLIENT_ID=...
FGRE_MAILBOX_GOOGLE_CLIENT_SECRET=...
FGRE_MAILBOX_GOOGLE_REFRESH_TOKEN=...
```

## Turn it on

```env
FGRE_INBOX_ENABLED=true
```

Run the scheduler (`php artisan schedule:work` locally, a cron entry calling `schedule:run` every
minute in production) and a queue worker (`php artisan queue:work`).

## Per-hotel settings

A report is accepted only when its sender is in the hotel's **allowed sender emails**. Fandaqah
PMS sends every hotel's Manager Flash from the same address, so FGRE then picks the hotel by the
name printed in the report and in the subject (`Manager Flash – {hotel} – {date}`). Make sure each
hotel's English and Arabic names match the PMS, or add the PMS name as an alias.
