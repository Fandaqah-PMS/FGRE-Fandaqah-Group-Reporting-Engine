<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A link to choose a password: for a new user added to a group (an invitation), or for
 * "forgot password". Branded email in the user's language; the link expires
 * (config auth.passwords.users.expire, 60 minutes by default; invitations 7 days).
 */
class SetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token, public ?string $invitedTo = null, public ?string $invitedBy = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?: 'ar';
        $url = url(route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset(), ...($this->invitedTo ? ['invite' => 1] : [])], false));
        $subject = $this->invitedTo
            ? __('You are invited to :group on FGRE', ['group' => $this->invitedTo], $locale)
            : __('Reset your FGRE password', [], $locale);

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.set-password', [
                'locale' => $locale,
                'name' => $notifiable->name,
                'url' => $url,
                'invitedTo' => $this->invitedTo,
                'invitedBy' => $this->invitedBy,
            ]);
    }
}
