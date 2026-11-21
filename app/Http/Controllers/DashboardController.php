<?php

namespace App\Http\Controllers;

use App\Enums\StatPeriod;
use App\Reports\Kpi\GroupKpiService;
use App\Support\HotelScope;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Group (or single-hotel) overview for one business date. */
class DashboardController extends Controller
{
    public const PERIODS = ['day' => StatPeriod::Day, 'mtd' => StatPeriod::MonthToDate, 'ytd' => StatPeriod::YearToDate];

    public const LAST_YEAR = ['day' => StatPeriod::DayLastYear, 'mtd' => StatPeriod::MonthToDateLastYear, 'ytd' => StatPeriod::YearToDateLastYear];

    public function __invoke(Request $request, GroupKpiService $kpis): Response
    {
        Gate::authorize('view dashboard');

        $groupId = $request->user()->current_group_id;
        $propertyIds = HotelScope::propertyIds($request);

        $latest = $kpis->latestDate($groupId, $propertyIds);
        $date = $this->dateParam($request) ?? $latest;
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : 'day';

        if ($date === null) {
            return Inertia::render('Dashboard', ['empty' => true]);
        }

        $ty = $kpis->forDate($groupId, $date, self::PERIODS[$period], $propertyIds);
        $ly = $kpis->forDate($groupId, $date, self::LAST_YEAR[$period], $propertyIds);
        $lyByHotel = collect($ly['hotels'])->keyBy('property_id');

        // Month to date (for the cumulative chart and month cards), whatever period is selected.
        $mtd = $period === 'mtd' ? $ty : $kpis->forDate($groupId, $date, StatPeriod::MonthToDate, $propertyIds);
        $mtdLy = $period === 'mtd' ? $ly : $kpis->forDate($groupId, $date, StatPeriod::MonthToDateLastYear, $propertyIds);

        return Inertia::render('Dashboard', [
            'empty' => false,
            'filters' => ['date' => $date, 'period' => $period, 'latest' => $latest],
            'currency' => $ty['currency'],
            'group' => $ty['group'],
            'groupLy' => $ly['group'],
            'hotels' => collect($ty['hotels'])->map(fn (array $h) => [...$h, 'ly' => $lyByHotel->get($h['property_id'])])->values(),
            'missing' => $ty['missing'],
            'hotelCount' => $propertyIds->count(),
            'trend' => $kpis->trend($groupId, CarbonImmutable::parse($date)->subDays(29)->toDateString(), $date, $propertyIds),
            'monthly' => $kpis->monthly($groupId, $date, $propertyIds),
            'month' => $kpis->trend($groupId, CarbonImmutable::parse($date)->startOfMonth()->toDateString(), $date, $propertyIds),
            'mtd' => $mtd['group'],
            'mtdLy' => $mtdLy['group'],
            'heatmap' => $propertyIds->count() > 1 ? $kpis->occupancyHeatmap($groupId, $date, $propertyIds) : null,
        ]);
    }
}
