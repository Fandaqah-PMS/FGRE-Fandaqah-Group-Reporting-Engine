<?php

namespace App\Reports\Kpi;

use App\Models\BudgetMonth;
use App\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Budget to date, the hospitality-standard way: a monthly budget is spread evenly over
 * its days. Day = month ÷ days in month; MTD = month × days elapsed ÷ days in month;
 * YTD = full months before this one + this month's MTD share. Rooms available for the
 * budget = rooms in hotel × days, so budget occupancy, ADR and RevPAR use the same
 * USALI formulas as actuals (KpiCalculator).
 */
class BudgetService
{
    public const PERIODS = ['day', 'mtd', 'ytd'];

    /**
     * @param  Collection<int, int>|array<int>  $propertyIds
     * @return array<int, array<string, array<string, mixed>|null>> [propertyId][period] = parts + KPIs, or null without a budget
     */
    public function forHotels(int $groupId, string $date, Collection|array $propertyIds): array
    {
        $d = CarbonImmutable::parse($date);
        $ids = collect($propertyIds)->values();
        $rooms = Property::forGroup($groupId)->whereIn('id', $ids)->pluck('total_rooms', 'id');
        $months = BudgetMonth::forGroup($groupId)->whereIn('property_id', $ids)
            ->whereDate('month', '>=', $d->startOfYear()->toDateString())
            ->whereDate('month', '<=', $d->startOfMonth()->toDateString())
            ->get()->groupBy('property_id');

        $out = [];
        foreach ($ids as $id) {
            $byMonth = ($months->get($id) ?? collect())->keyBy(fn ($b) => $b->month->format('Y-m'));
            $current = $byMonth->get($d->format('Y-m'));
            foreach (self::PERIODS as $period) {
                $out[$id][$period] = $current === null ? null : $this->period($byMonth, $d, $period, (int) ($rooms[$id] ?? 0));
            }
        }

        return $out;
    }

    /**
     * Sum the parts of several hotels' budgets (for a region or the group) and recompute KPIs.
     *
     * @param  list<array<string, mixed>|null>  $budgets
     * @return array<string, mixed>|null
     */
    public static function combine(array $budgets): ?array
    {
        $present = array_values(array_filter($budgets));
        if ($present === []) {
            return null;
        }
        $parts = KpiCalculator::sumParts($present);

        return [...$parts, ...KpiCalculator::compute($parts)];
    }

    private function period(Collection $byMonth, CarbonImmutable $d, string $period, int $rooms): array
    {
        $dim = $d->daysInMonth;
        $share = match ($period) {
            'day' => 1 / $dim, default => $d->day / $dim
        };
        $parts = ['total_rooms' => 0, 'rooms_occupied' => 0, 'room_revenue' => 0.0, 'fnb_revenue' => 0.0, 'other_revenue' => 0.0, 'total_revenue' => 0.0];

        $add = function ($budget, float $fraction, int $days) use (&$parts, $rooms) {
            $parts['total_rooms'] += (int) round($rooms * $days);
            $parts['rooms_occupied'] += (int) round($budget->rooms_sold * $fraction);
            foreach (['room_revenue', 'fnb_revenue', 'other_revenue', 'total_revenue'] as $k) {
                $parts[$k] += (float) $budget->{$k} * $fraction;
            }
        };

        $add($byMonth->get($d->format('Y-m')), $share, $period === 'day' ? 1 : $d->day);

        if ($period === 'ytd') {
            for ($m = $d->startOfYear(); $m->lt($d->startOfMonth()); $m = $m->addMonth()) {
                if ($b = $byMonth->get($m->format('Y-m'))) {
                    $add($b, 1.0, $m->daysInMonth);
                }
            }
        }

        foreach (['room_revenue', 'fnb_revenue', 'other_revenue', 'total_revenue'] as $k) {
            $parts[$k] = round($parts[$k], 2);
        }

        return [...$parts, ...KpiCalculator::compute($parts)];
    }
}
