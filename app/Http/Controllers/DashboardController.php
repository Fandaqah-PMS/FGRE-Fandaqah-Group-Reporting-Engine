<?php

namespace App\Http\Controllers;

use App\Enums\StatPeriod;
use App\Reports\Kpi\GroupKpiService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public const PERIODS = ['day' => StatPeriod::Day, 'mtd' => StatPeriod::MonthToDate, 'ytd' => StatPeriod::YearToDate];

    public const LAST_YEAR = ['day' => StatPeriod::DayLastYear, 'mtd' => StatPeriod::MonthToDateLastYear, 'ytd' => StatPeriod::YearToDateLastYear];

    public function __invoke(Request $request, GroupKpiService $kpis): Response
    {
        Gate::authorize('view dashboard');

        $user = $request->user();
        $groupId = $user->current_group_id;
        $propertyIds = $user->accessiblePropertyIds();

        $latest = $kpis->latestDate($groupId, $propertyIds);
        $date = $this->validDate($request->query('date')) ?? $latest;
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : 'day';

        if ($date === null) {
            return Inertia::render('Dashboard', ['empty' => true, 'hotelCount' => $propertyIds->count()]);
        }

        $ty = $kpis->forDate($groupId, $date, self::PERIODS[$period], $propertyIds);
        $ly = $kpis->forDate($groupId, $date, self::LAST_YEAR[$period], $propertyIds);
        $lyByHotel = collect($ly['hotels'])->keyBy('property_id');

        $hotels = collect($ty['hotels'])->map(fn (array $h) => [
            ...$h,
            'ly' => $lyByHotel->get($h['property_id']),
        ])->values();

        $trendFrom = CarbonImmutable::parse($date)->subDays(29)->toDateString();

        return Inertia::render('Dashboard', [
            'empty' => false,
            'filters' => ['date' => $date, 'period' => $period, 'latest' => $latest],
            'currency' => $ty['currency'],
            'group' => $ty['group'],
            'groupLy' => $ly['group'],
            'hotels' => $hotels,
            'missing' => $ty['missing'],
            'hotelCount' => $propertyIds->count(),
            'trend' => $kpis->trend($groupId, $trendFrom, $date, $propertyIds),
        ]);
    }

    private function validDate(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? $value : null;
    }
}
