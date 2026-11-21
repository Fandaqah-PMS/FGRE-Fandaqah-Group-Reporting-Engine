<?php

namespace App\Reports;

use App\Enums\StatPeriod;
use App\Reports\Kpi\GroupKpiService;
use Illuminate\Support\Collection;

/**
 * Data for the morning email: last business date, Day and MTD, one column per hotel
 * plus the group, each figure with its change vs last year (USALI KPIs).
 */
class MorningFlashBuilder
{
    public const ROWS = [
        ['occupancy_pct', 'Occupancy', 'pct'],
        ['adr', 'ADR', 'money'],
        ['revpar', 'RevPAR', 'money'],
        ['paid_occupied', 'Rooms sold', 'int'],
        ['room_revenue', 'Room revenue', 'money0'],
        ['total_revenue', 'Total revenue', 'money0'],
    ];

    public function __construct(private readonly GroupKpiService $kpis, private readonly AttentionService $attention) {}

    /**
     * @param  Collection<int, int>  $propertyIds
     * @return array<string, mixed>|null null when there is nothing to send
     */
    public function build(int $groupId, Collection $propertyIds): ?array
    {
        $date = $this->kpis->latestDate($groupId, $propertyIds);
        if ($date === null) {
            return null;
        }

        $periods = [];
        foreach (['day' => [StatPeriod::Day, StatPeriod::DayLastYear], 'mtd' => [StatPeriod::MonthToDate, StatPeriod::MonthToDateLastYear]] as $key => [$ty, $ly]) {
            $now = $this->kpis->forDate($groupId, $date, $ty, $propertyIds);
            $before = $this->kpis->forDate($groupId, $date, $ly, $propertyIds);
            $periods[$key] = [
                'hotels' => collect($now['hotels'])->map(fn ($h) => ['ty' => $h, 'ly' => collect($before['hotels'])->firstWhere('property_id', $h['property_id'])])->values()->all(),
                'group' => ['ty' => $now['group'], 'ly' => $before['group']],
                'missing' => $now['missing'],
                'currency' => $now['currency'],
            ];
        }

        return [
            'date' => $date,
            'periods' => $periods,
            'attention' => array_slice($this->attention->items($groupId, $date, $propertyIds), 0, 6),
        ];
    }
}
