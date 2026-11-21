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

        $properties = Property::forGroup($groupId)->whereIn('id', $propertyIds)->orderBy('name')->get();

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
                $missing[] = ['id' => $property->id, 'name' => $property->name];

                continue;
            }

            $parts = KpiCalculator::sumParts([$stat]);
            $hotels[] = [
                'property_id' => $property->id,
                'name' => $property->name,
                'name_ar' => $property->name_ar,
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
}
