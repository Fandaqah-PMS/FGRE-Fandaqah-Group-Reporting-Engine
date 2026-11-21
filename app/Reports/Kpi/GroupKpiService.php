<?php

namespace App\Reports\Kpi;

use App\Enums\StatPeriod;
use App\Models\DailyPropertyStat;
use App\Models\Property;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Group KPIs for one business date and period (Day / MTD / YTD, this year or
 * last year): one row per hotel plus a group total recomputed from summed
 * parts. Dashboards call this; they never compute KPIs themselves.
 */
class GroupKpiService
{
    /**
     * @param  Collection<int, int>|array<int>  $propertyIds  hotels the viewer may see
     * @return array{date: string, period: string, hotels: list<array<string, mixed>>, group: array<string, mixed>, missing: list<array{id: int, name: string}>}
     */
    public function forDate(int $groupId, CarbonInterface|string $date, StatPeriod $period, Collection|array $propertyIds): array
    {
        $date = is_string($date) ? $date : $date->toDateString();
        $propertyIds = collect($propertyIds)->values();

        $properties = Property::forGroup($groupId)->with('pmsProvider')->whereIn('id', $propertyIds)->orderBy('name')->get();

        $stats = DailyPropertyStat::forGroup($groupId)
            ->whereIn('property_id', $propertyIds)
            ->whereDate('business_date', $date)
            ->where('period', $period->value)
            ->get()
            ->keyBy('property_id');

        $hotels = [];
        $missing = [];
        foreach ($properties as $property) {
            $stat = $stats->get($property->id);
            if ($stat === null) {
                $missing[] = ['id' => $property->id, 'name' => $property->name, 'name_ar' => $property->name_ar];

                continue;
            }

            $parts = KpiCalculator::sumParts([$stat]);
            $hotels[] = [
                'property_id' => $property->id,
                'name' => $property->name,
                'name_ar' => $property->name_ar,
                'pms' => $property->pmsProvider?->name,
                'is_provisional' => $stat->is_provisional,
                'currency' => $stat->currency,
                ...$parts,
                ...KpiCalculator::compute($parts),
            ];
        }

        $groupParts = KpiCalculator::sumParts($stats->values());

        return [
            'date' => $date,
            'period' => $period->value,
            'currency' => $properties->first()?->currency ?? 'SAR',
            'hotels' => $hotels,
            'group' => [
                'hotel_count' => count($hotels),
                'is_provisional' => $stats->contains('is_provisional', true),
                ...$groupParts,
                ...KpiCalculator::compute($groupParts),
            ],
            'missing' => $missing,
        ];
    }

    /**
     * Daily KPIs over a date range, this year and the same days last year, for the given
     * hotels summed together (one hotel = that hotel's trend).
     *
     * @param  Collection<int, int>|array<int>  $propertyIds
     * @return list<array{date: string, occupancy_pct: ?string, adr: ?string, revpar: ?string, total_revenue: ?string, ly_occupancy_pct: ?string, ly_adr: ?string, ly_revpar: ?string, ly_total_revenue: ?string, hotels: int}>
     */
    public function trend(int $groupId, string $from, string $to, Collection|array $propertyIds): array
    {
        $rows = DailyPropertyStat::forGroup($groupId)
            ->whereIn('property_id', collect($propertyIds)->values())
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to)
            ->whereIn('period', [StatPeriod::Day->value, StatPeriod::DayLastYear->value])
            ->get()
            ->groupBy(fn (DailyPropertyStat $s) => $s->business_date->toDateString());

        $series = [];
        foreach ($rows->sortKeys() as $date => $stats) {
            [$ty, $ly] = $stats->partition(fn (DailyPropertyStat $s) => $s->period === StatPeriod::Day);
            $tyParts = KpiCalculator::sumParts($ty->values());
            $lyParts = KpiCalculator::sumParts($ly->values());
            $tyKpi = KpiCalculator::compute($tyParts);
            $lyKpi = KpiCalculator::compute($lyParts);

            $series[] = [
                'date' => $date,
                'occupancy_pct' => $tyKpi['occupancy_pct'],
                'adr' => $tyKpi['adr'],
                'revpar' => $tyKpi['revpar'],
                'total_revenue' => $tyParts['total_revenue'],
                'ly_occupancy_pct' => $lyKpi['occupancy_pct'],
                'ly_adr' => $lyKpi['adr'],
                'ly_revpar' => $lyKpi['revpar'],
                'ly_total_revenue' => $lyParts['total_revenue'],
                'hotels' => $ty->count(),
            ];
        }

        return $series;
    }

    /** Latest business date with a day row for any of the hotels, or null. */
    public function latestDate(int $groupId, Collection|array $propertyIds): ?string
    {
        $date = DailyPropertyStat::forGroup($groupId)
            ->whereIn('property_id', collect($propertyIds)->values())
            ->where('period', StatPeriod::Day->value)
            ->max('business_date');

        return $date === null ? null : substr((string) $date, 0, 10);
    }
}
