<?php

namespace App\Mail;

use App\Models\HotelGroup;
use App\Models\ReportSubscription;
use App\Reports\Subscriptions\SubscriptionReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A subscription email: the group report for a day, a week or a month, as charts and/or tables. */
class SubscriptionReportMail extends Mailable
{
    use Queueable;

    /** @param array<string, mixed> $report from SubscriptionReportBuilder */
    public function __construct(public HotelGroup $group, public ReportSubscription $subscription, public array $report)
    {
        $this->locale($subscription->locale ?: 'ar');
    }

    public function envelope(): Envelope
    {
        $name = $this->subscription->locale === 'en' ? $this->group->name : ($this->group->name_ar ?: $this->group->name);

        return new Envelope(subject: __(ucfirst($this->report['frequency']).' group report', [], $this->subscription->locale).' – '.$name.' – '.$this->periodLabel());
    }

    public function content(): Content
    {
        return new Content(view: 'emails.subscription-report', with: ['periodLabel' => $this->periodLabel()]);
    }

    /** Tables subscribers also get the figures as a CSV file (opens in Excel). */
    public function attachments(): array
    {
        if (! $this->subscription->wantsTables()) {
            return [];
        }

        return [Attachment::fromData(fn () => $this->csv(), "group-report-{$this->report['to']}.csv")->withMime('text/csv')];
    }

    public function periodLabel(): string
    {
        $locale = $this->subscription->locale ?: 'ar';

        return match ($this->report['frequency']) {
            'weekly' => $this->report['from'].' → '.$this->report['to'],
            'monthly' => CarbonImmutable::parse($this->report['from'])->locale($locale)->translatedFormat('F Y'),
            default => $this->report['to'],
        };
    }

    private function csv(): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");   // BOM: Excel opens UTF-8 (Arabic names) correctly
        fputcsv($out, ['Period', 'Hotel', 'Metric', 'This year', 'Last year'], escape: '');
        foreach ($this->report['sections'] as $period => $section) {
            foreach ([...$section['hotels'], ['ty' => ['name' => 'Group'] + $section['group']['ty'], 'ly' => $section['group']['ly']]] as $h) {
                foreach (SubscriptionReportBuilder::ROWS as [$field, $label]) {
                    fputcsv($out, [$period, $h['ty']['name'], $label, $h['ty'][$field] ?? '', $h['ly'][$field] ?? ''], escape: '');
                }
            }
        }
        rewind($out);

        return (string) stream_get_contents($out);
    }
}
