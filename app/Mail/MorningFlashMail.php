<?php

namespace App\Mail;

use App\Models\HotelGroup;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The group Manager Flash for the last business date, hotels side by side, in the user's language. */
class MorningFlashMail extends Mailable
{
    use Queueable;

    /** @param array<string, mixed> $report from MorningFlashBuilder */
    public function __construct(public HotelGroup $group, public User $user, public array $report)
    {
        $this->locale($user->locale ?: 'ar');
    }

    public function envelope(): Envelope
    {
        $name = $this->user->locale === 'en' ? $this->group->name : ($this->group->name_ar ?: $this->group->name);

        return new Envelope(subject: __('Group flash').' – '.$name.' – '.$this->report['date']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.morning-flash');
    }
}
