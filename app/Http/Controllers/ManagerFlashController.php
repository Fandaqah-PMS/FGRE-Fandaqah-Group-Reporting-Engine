<?php

namespace App\Http\Controllers;

use App\Enums\StatPeriod;
use App\Models\Property;
use App\Reports\Kpi\BudgetService;
use App\Reports\Kpi\GroupKpiService;
use App\Support\HotelScope;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manager Flash for one business date, for the hotels in view:
 * - columns: the six periods (Day / MTD / YTD, this year and last year) for the group
 *   (parts summed, KPIs recomputed);
 * - hotels: the same per hotel; regions: the same per hotel set (subtotals);
 * - budget: budget to date per hotel, region and group (Day / MTD / YTD).
 * `hotels` (comma-separated ids) narrows the view to some hotels.
 */
class ManagerFlashController extends Controller
{
    public function __invoke(Request $request, GroupKpiService $kpis, BudgetService $budgets): Response
    {
        Gate::authorize('view reports');

        return Inertia::render('ManagerFlash', $this->data($request, $kpis, $budgets));
    }

    /** @return array<string, mixed> */
    public function data(Request $request, GroupKpiService $kpis, BudgetService $budgets): array
    {
        $groupId = $request->user()->current_group_id;
        $available = HotelScope::propertyIds($request);
        $propertyIds = $this->selection($request, $available);
        $latest = $kpis->latestDate($groupId, $available);
        $date = $this->dateParam($request) ?? $latest;

        if ($date === null) {
            return ['empty' => true];
        }

        $properties = Property::forGroup($groupId)->whereIn('id', $propertyIds)->get(['id', 'region']);
        $regions = $properties->whereNotNull('region')->groupBy('region')->map(fn ($p) => $p->pluck('id'))->sortKeys();

        $columns = [];
        $hotels = [];
        $regionData = [];
        $currency = 'SAR';
        $missing = [];
        foreach (StatPeriod::cases() as $period) {
            $result = $kpis->forDate($groupId, $date, $period, $propertyIds);
            $columns[$period->value] = $result['group']['hotel_count'] > 0 ? $result['group'] : null;
            $currency = $result['currency'];
            if ($period === StatPeriod::Day) {
                $missing = $result['missing'];
            }
            foreach ($result['hotels'] as $h) {
                $hotels[$h['property_id']] ??= [
                    'id' => $h['property_id'], 'name' => $h['name'], 'name_ar' => $h['name_ar'], 'pms' => $h['pms'],
                    'region' => $properties->firstWhere('id', $h['property_id'])?->region, 'periods' => [],
                ];
                $hotels[$h['property_id']]['periods'][$period->value] = $h;
            }
            if ($regions->count() > 1) {
                foreach ($regions as $region => $ids) {
                    $regionData[$region]['key'] = $region;
                    $regionData[$region]['hotelIds'] = $ids->values();
                    $regionData[$region]['periods'][$period->value] = $kpis->forDate($groupId, $date, $period, $ids)['group'];
                }
            }
        }

        $hotelBudgets = $budgets->forHotels($groupId, $date, $propertyIds);
        $budget = ['hotels' => $hotelBudgets, 'regions' => [], 'group' => []];
        // Like for like: group and set budgets only count hotels that have figures for the period.
        foreach (BudgetService::PERIODS as $p) {
            $reporting = collect($hotels)->filter(fn ($h) => isset($h['periods'][$p]))->keys();
            $budget['group'][$p] = BudgetService::combine($reporting->map(fn ($id) => $hotelBudgets[$id][$p] ?? null)->all());
            foreach ($regions as $region => $ids) {
                $budget['regions'][$region][$p] = BudgetService::combine($ids->intersect($reporting)->map(fn ($id) => $hotelBudgets[$id][$p] ?? null)->all());
            }
        }

        return [
            'empty' => false,
            'date' => $date,
            'latest' => $latest,
            'currency' => $currency,
            'columns' => $columns,
            'hotels' => array_values($hotels),
            'regions' => array_values($regionData),
            'budget' => $budget,
            'missing' => $missing,
            'hotelCount' => $propertyIds->count(),
            'selection' => $request->filled('hotels') ? $propertyIds->values() : null,
        ];
    }

    /** @return Collection<int, int> the requested hotels that the user may see (all of them by default) */
    private function selection(Request $request, Collection $available): Collection
    {
        $requested = collect(explode(',', (string) $request->query('hotels')))->filter(fn ($v) => ctype_digit($v))->map(fn ($v) => (int) $v);
        $chosen = $available->intersect($requested)->values();

        return $chosen->isEmpty() ? $available : $chosen;
    }
}
