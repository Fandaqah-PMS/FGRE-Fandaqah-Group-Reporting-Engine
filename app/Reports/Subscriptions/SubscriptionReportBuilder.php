<?php

namespace App\Reports\Subscriptions;

use App\Enums\StatPeriod;
use App\Reports\AttentionService;
use App\Reports\Kpi\GroupKpiService;
use Illuminate\Support\Collection;

/**
 * Data for a subscription email: the period it covers (a day, the last 7 days or the
 * previous month) plus a running total (month to date, or year to date for monthly),
 * one column per hotel and the group, each figure next to last year (USALI KPIs).
 */
class SubscriptionReportBuilder
{
    /** Rows of the side-by-side table: field, label, format. */
    public const ROWS = [
        ['occupancy_pct', 'Occupancy', 'pct'],
        ['adr', 'ADR', 'money'],
        ['revpar', 'RevPAR', 'money'],
        ['paid_occupied', 'Rooms sold', 'int'],
        ['room_revenue', 'Room revenue', 'money0'],
        ['total_revenue', 'Total revenue', 'money0'],
    ];

    /** Hotel bar charts: field, title, format. */
    public const CHARTS = [
        ['total_revenue', 'Total revenue by hotel', 'money0'],
        ['occupancy_pct', 'Occupancy by hotel', 'pct'],
        ['revpar', 'RevPAR by hotel', 'money'],
    ];

    public function __construct(private readonly GroupKpiService $kpis, private readonly AttentionService $attention) {}

    /**
     * @param  Collection<int, int>  $propertyIds
     * @param  array{from: string, to: string}  $range
     * @return array<string, mixed>|null null when the period has no data
     */
    public function build(int $groupId, Collection $propertyIds, string $frequency, array $range, bool $withAttention = true): ?array
    {
        $to = $range['to'];

        $main = match ($frequency) {
            'daily' => ['day', $this->forDate($groupId, $to, StatPeriod::Day, StatPeriod::DayLastYear, $propertyIds)],
            'weekly' => ['week', $this->kpis->forRange($groupId, $range['from'], $to, $propertyIds)],
            default => ['month', $this->kpis->forRange($groupId, $range['from'], $to, $propertyIds)],
        };
        if ($main[1]['hotels'] === []) {
            return null;
        }

        $running = $frequency === 'monthly'
            ? ['ytd', $this->forDate($groupId, $to, StatPeriod::YearToDate, StatPeriod::YearToDateLastYear, $propertyIds)]
            : ['mtd', $this->forDate($groupId, $to, StatPeriod::MonthToDate, StatPeriod::MonthToDateLastYear, $propertyIds)];

        $sections = [$main[0] => $main[1]];
        if ($running[1]['hotels'] !== []) {
            $sections[$running[0]] = $running[1];
        }

        return [
            'frequency' => $frequency,
            'from' => $range['from'],
            'to' => $to,
            'main' => $main[0],
            'sections' => $sections,
            'trend' => $frequency === 'daily' ? [] : $this->kpis->trend($groupId, $range['from'], $to, $propertyIds),
            'attention' => $withAttention ? array_slice($this->attention->items($groupId, $to, $propertyIds), 0, 8) : [],
        ];
    }

    /** One business date, in the same shape as GroupKpiService::forRange. */
    private function forDate(int $groupId, string $date, StatPeriod $ty, StatPeriod $ly, Collection $propertyIds): array
    {
        $now = $this->kpis->forDate($groupId, $date, $ty, $propertyIds);
        $before = $this->kpis->forDate($groupId, $date, $ly, $propertyIds);
        $lyHotels = collect($before['hotels'])->keyBy('property_id');

        return [
            'hotels' => collect($now['hotels'])->map(fn ($h) => ['ty' => $h, 'ly' => $lyHotels->get($h['property_id'])])->values()->all(),
            'group' => ['ty' => $now['group'], 'ly' => $before['hotels'] === [] ? null : $before['group']],
            'missing' => $now['missing'],
            'currency' => $now['currency'],
        ];
    }
}
