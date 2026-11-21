<?php

namespace App\Console\Commands;

use App\Models\ReportSubscription;
use App\Reports\Subscriptions\SubscriptionMailer;
use App\Tenancy\GroupScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendSubscriptions extends Command
{
    protected $signature = 'fgre:send-subscriptions {--subscription= : Send this subscription now, whatever its schedule}';

    protected $description = 'Email the daily / weekly / monthly group reports that are due';

    public function handle(SubscriptionMailer $mailer): int
    {
        // The scheduler serves every group, so the group scope is bypassed on purpose;
        // each subscription carries its own group and is checked against its user's access.
        $query = ReportSubscription::withoutGlobalScope(GroupScope::class)->with(['user', 'group']);

        if ($id = $this->option('subscription')) {
            $subscription = $query->findOrFail($id);
            $this->info($mailer->sendNow($subscription) ? 'Sent.' : 'Nothing to send: no data for this period.');

            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($query->where('is_active', true)->get() as $subscription) {
            try {
                $sent += (int) $mailer->sendIfDue($subscription, now());
            } catch (Throwable $e) {
                Log::error('Subscription email failed', ['subscription' => $subscription->id, 'error' => $e->getMessage()]);
                report($e);
            }
        }

        $this->info("{$sent} email(s) sent.");

        return self::SUCCESS;
    }
}
