<?php

namespace App\Reports\Subscriptions;

use App\Mail\SubscriptionReportMail;
use App\Models\ReportSubscription;
use App\Reports\Kpi\GroupKpiService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Builds and sends subscription emails. Runs from the scheduler (no logged-in user):
 * the group always comes from the subscription, and the hotels are re-checked against
 * what the user may see today, so a removed hotel or role stops the email.
 */
class SubscriptionMailer
{
    public function __construct(private readonly GroupKpiService $kpis, private readonly SubscriptionReportBuilder $builder) {}

    /** Sends the subscription when it is due. Returns true when an email went out. */
    public function sendIfDue(ReportSubscription $s, CarbonInterface $now): bool
    {
        $local = $this->local($s, $now);
        $ids = $this->hotels($s);
        if ($ids->isEmpty()) {
            return false;
        }

        $latest = $this->kpis->latestDate($s->group_id, $ids);
        $key = SubscriptionSchedule::dueKey($s, $local, $latest);
        if ($key === null) {
            return false;
        }

        $mail = $this->mailable($s, $local, $ids, $latest);
        if ($mail !== null) {
            Mail::to($s->user)->send($mail);
        }

        // A weekly or monthly slot is used even without data, so it is not retried every 15 minutes.
        $s->forceFill(['last_period_key' => $key, 'last_sent_at' => $mail ? now() : $s->last_sent_at])->save();

        return $mail !== null;
    }

    /** Sends the latest period now ("send me one now"); does not change the schedule. */
    public function sendNow(ReportSubscription $s): bool
    {
        $mail = $this->mailable($s);
        if ($mail !== null) {
            Mail::to($s->user)->send($mail);
        }

        return $mail !== null;
    }

    /** The email for the latest period, or null when there is no data. Also used for previews. */
    public function mailable(ReportSubscription $s, ?CarbonImmutable $local = null, ?Collection $ids = null, ?string $latest = null): ?SubscriptionReportMail
    {
        $local ??= $this->local($s, now());
        $ids ??= $this->hotels($s);
        if ($ids->isEmpty()) {
            return null;
        }
        $latest ??= $this->kpis->latestDate($s->group_id, $ids);

        $range = SubscriptionSchedule::range($s, $local, $latest);
        $report = $range === null ? null : $this->builder->build($s->group_id, $ids, $s->frequency, $range, $s->include_attention);

        return $report === null ? null : new SubscriptionReportMail($s->group, $s, $report);
    }

    /** @return Collection<int, int> */
    private function hotels(ReportSubscription $s): Collection
    {
        $user = $s->user;
        if ($user === null || ! $user->hasGroupPermission('view reports', $s->group_id)) {
            return collect();
        }

        $ids = $user->accessiblePropertyIds($s->group_id);

        return $s->hotels ? $ids->intersect(array_map('intval', $s->hotels))->values() : $ids->values();
    }

    private function local(ReportSubscription $s, CarbonInterface $now): CarbonImmutable
    {
        return CarbonImmutable::instance($now)->setTimezone($s->group?->timezone ?: 'Asia/Riyadh');
    }
}
