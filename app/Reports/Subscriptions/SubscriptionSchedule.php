<?php

namespace App\Reports\Subscriptions;

use App\Models\ReportSubscription;
use Carbon\CarbonImmutable;

/**
 * When a subscription is due and which days its email covers.
 *
 *   daily    after send_time, once per business date (the night audit may arrive late:
 *            the email goes out as soon as the new date is in, never twice for one date)
 *   weekly   on the chosen weekday after send_time: the 7 days ending the last business date
 *   monthly  on the chosen day of the month after send_time: the previous calendar month
 *
 * All times are in the group's timezone.
 */
final class SubscriptionSchedule
{
    /** The period key to send now, or null when nothing is due. */
    public static function dueKey(ReportSubscription $s, CarbonImmutable $nowLocal, ?string $latestDate): ?string
    {
        if (! $s->is_active || $nowLocal->format('H:i') < $s->send_time) {
            return null;
        }

        $key = match ($s->frequency) {
            'daily' => $latestDate === null ? null : 'D'.$latestDate,
            'weekly' => $nowLocal->dayOfWeek === (int) $s->weekday ? 'W'.$nowLocal->toDateString() : null,
            'monthly' => $nowLocal->day === (int) $s->month_day ? 'M'.$nowLocal->format('Y-m') : null,
            default => null,
        };

        return $key !== null && $key !== $s->last_period_key ? $key : null;
    }

    /**
     * Days the email covers, or null when there is no data for them yet.
     *
     * @return array{from: string, to: string}|null
     */
    public static function range(ReportSubscription $s, CarbonImmutable $nowLocal, ?string $latestDate): ?array
    {
        if ($latestDate === null) {
            return null;
        }
        $latest = CarbonImmutable::parse($latestDate);

        [$from, $to] = match ($s->frequency) {
            'weekly' => [$latest->subDays(6), $latest],
            'monthly' => [$nowLocal->startOfMonth()->subMonth(), $nowLocal->startOfMonth()->subMonth()->endOfMonth()->min($latest)],
            default => [$latest, $latest],
        };

        return $to->lt($from) ? null : ['from' => $from->toDateString(), 'to' => $to->toDateString()];
    }
}
