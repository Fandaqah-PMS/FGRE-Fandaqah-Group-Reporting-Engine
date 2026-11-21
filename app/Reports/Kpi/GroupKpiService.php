<?php

namespace App\Reports\Kpi;

use App\Enums\StatPeriod;
use App\Models\DailyPropertyStat;
use App\Models\Property;
use Carbon\CarbonImmutable;
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
                'room_revenue' => $tyParts['room_revenue'],
                'arrival_rooms' => $tyParts['arrival_rooms'],
                'departure_rooms' => $tyParts['departure_rooms'],
                'ly_room_revenue' => $lyParts['room_revenue'],
                'ly_occupancy_pct' => $lyKpi['occupancy_pct'],
                'ly_adr' => $lyKpi['adr'],
                'ly_revpar' => $lyKpi['revpar'],
                'ly_total_revenue' => $lyParts['total_revenue'],
                'hotels' => $ty->count(),
            ];
        }

        return $series;
    }

    /**
     * Month by month for the 12 months ending with $date's month: revenue and KPIs,
     * this year and the same months last year (from the day_ly rows).
     *
     * @param  Collection<int, int>|array<int>  $propertyIds
     * @return list<array<string, mixed>>
     */
    public function monthly(int $groupId, string $date, Collection|array $propertyIds, int $months = 12): array
    {
        $end = CarbonImmutable::parse($date);
        $start = $end->startOfMonth()->subMonths($months - 1);

        $buckets = [];
        for ($m = $start; $m->lte($end); $m = $m->addMonth()) {
            $buckets[$m->format('Y-m')] = ['ty' => [], 'ly' => []];
        }

        DailyPropertyStat::forGroup($groupId)
            ->whereIn('property_id', collect($propertyIds)->values())
            ->whereDate('business_date', '>=', $start->toDateString())
            ->whereDate('business_date', '<=', $end->toDateString())
            ->whereIn('period', [StatPeriod::Day->value, StatPeriod::DayLastYear->value])
            ->select(['business_date', 'period', ...KpiCalculator::PARTS])
            ->lazy(2000)
            ->each(function (DailyPropertyStat $s) use (&$buckets) {
                $month = $s->business_date->format('Y-m');
                $buckets[$month][$s->period === StatPeriod::Day ? 'ty' : 'ly'][] = $s->getAttributes();
            });

        $out = [];
        foreach ($buckets as $month => $rows) {
            $ty = KpiCalculator::sumParts($rows['ty']);
            $ly = KpiCalculator::sumParts($rows['ly']);
            $tyKpi = KpiCalculator::compute($ty);
            $lyKpi = KpiCalculator::compute($ly);
            $out[] = [
                'month' => $month,
                'days' => count(array_unique(array_column($rows['ty'], 'business_date'))),
                'total_revenue' => $ty['total_revenue'],
                'room_revenue' => $ty['room_revenue'],
                'occupancy_pct' => $tyKpi['occupancy_pct'],
                'adr' => $tyKpi['adr'],
                'revpar' => $tyKpi['revpar'],
                'ly_total_revenue' => $ly['total_revenue'],
                'ly_room_revenue' => $ly['room_revenue'],
                'ly_occupancy_pct' => $lyKpi['occupancy_pct'],
                'ly_adr' => $lyKpi['adr'],
                'ly_revpar' => $lyKpi['revpar'],
            ];
        }

        return $out;
    }

    /**
     * Daily headline occupancy per hotel for the last $days days up to $date.
     *
     * @param  Collection<int, int>|array<int>  $propertyIds
     * @return array{dates: list<string>, hotels: list<array{id: int, name: string, name_ar: ?string, values: array<string, ?string>}>}
     */
    public function occupancyHeatmap(int $groupId, string $date, Collection|array $propertyIds, int $days = 14): array
    {
        $end = CarbonImmutable::parse($date);
        $dates = [];
        for ($d = $end->subDays($days - 1); $d->lte($end); $d = $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        $properties = Property::forGroup($groupId)->whereIn('id', collect($propertyIds)->values())->orderBy('name')->get(['id', 'name', 'name_ar']);
        $stats = DailyPropertyStat::forGroup($groupId)
            ->whereIn('property_id', $properties->pluck('id'))
            ->whereDate('business_date', '>=', $dates[0])
            ->whereDate('business_date', '<=', $end->toDateString())
            ->where('period', StatPeriod::Day->value)
            ->get()
            ->groupBy('property_id');

        return [
            'dates' => $dates,
            'hotels' => $properties->map(function (Property $p) use ($stats, $dates) {
                $byDate = ($stats->get($p->id) ?? collect())->keyBy(fn ($s) => $s->business_date->toDateString());

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'name_ar' => $p->name_ar,
                    'values' => collect($dates)->mapWithKeys(fn (string $d) => [
                        $d => $byDate->has($d) ? KpiCalculator::compute(KpiCalculator::sumParts([$byDate[$d]]))['occupancy_pct'] : null,
                    ])->all(),
                ];
            })->values()->all(),
        ];
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
