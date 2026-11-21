<?php

namespace App\Http\Controllers;

use App\Enums\StatPeriod;
use App\Reports\Kpi\GroupKpiService;
use App\Support\HotelScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manager Flash for one business date: the six columns (Day / MTD / YTD, this
 * year and last year) for the selected hotel, or consolidated over all hotels
 * (parts summed, KPIs recomputed), plus a per-hotel breakdown.
 */
class ManagerFlashController extends Controller
{
    public function __invoke(Request $request, GroupKpiService $kpis): Response
    {
        Gate::authorize('view reports');

        $groupId = $request->user()->current_group_id;
        $propertyIds = HotelScope::propertyIds($request);
        $latest = $kpis->latestDate($groupId, $propertyIds);
        $date = $this->dateParam($request) ?? $latest;

        if ($date === null) {
            return Inertia::render('ManagerFlash', ['empty' => true]);
        }

        $columns = [];
        $hotels = [];
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
                $hotels[$h['property_id']] ??= ['id' => $h['property_id'], 'name' => $h['name'], 'name_ar' => $h['name_ar'], 'pms' => $h['pms'], 'periods' => []];
                $hotels[$h['property_id']]['periods'][$period->value] = $h;
            }
        }

        return Inertia::render('ManagerFlash', [
            'empty' => false,
            'date' => $date,
            'latest' => $latest,
            'currency' => $currency,
            'columns' => $columns,
            'hotels' => array_values($hotels),
            'missing' => $missing,
        ]);
    }
}
