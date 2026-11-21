<?php

namespace App\Http\Controllers;

use App\Enums\StatPeriod;
use App\Reports\AttentionService;
use App\Reports\Kpi\BudgetService;
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

    public function __invoke(Request $request, GroupKpiService $kpis, AttentionService $attention, BudgetService $budgets): Response
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
            'attention' => $attention->items($groupId, $date, $propertyIds),
            'ranking' => $propertyIds->count() > 1 ? $this->ranking($kpis, $budgets, $groupId, $date, $propertyIds, $mtd, $mtdLy) : null,
            'heatmap' => $propertyIds->count() > 1 ? $kpis->occupancyHeatmap($groupId, $date, $propertyIds) : null,
        ]);
    }

    /**
     * League table: each hotel's MTD and YTD key figures, change vs last year, and MTD
     * revenue vs budget. Ranked on the page by the column the owner picks.
     *
     * @return list<array<string, mixed>>
     */
    private function ranking(GroupKpiService $kpis, BudgetService $budgets, int $groupId, string $date, $propertyIds, array $mtd, array $mtdLy): array
    {
        $ytd = collect($kpis->forDate($groupId, $date, StatPeriod::YearToDate, $propertyIds)['hotels'])->keyBy('property_id');
        $ytdLy = collect($kpis->forDate($groupId, $date, StatPeriod::YearToDateLastYear, $propertyIds)['hotels'])->keyBy('property_id');
        $mtdLyBy = collect($mtdLy['hotels'])->keyBy('property_id');
        $budget = $budgets->forHotels($groupId, $date, $propertyIds);
        $trend = fn (int $id) => array_column($kpis->trend($groupId, CarbonImmutable::parse($date)->subDays(29)->toDateString(), $date, [$id]), 'revpar');

        return collect($mtd['hotels'])->map(fn (array $h) => [
            'id' => $h['property_id'], 'name' => $h['name'], 'name_ar' => $h['name_ar'], 'pms' => $h['pms'],
            'mtd' => $this->pick($h), 'mtd_ly' => $this->pick($mtdLyBy->get($h['property_id'])),
            'ytd' => $this->pick($ytd->get($h['property_id'])), 'ytd_ly' => $this->pick($ytdLy->get($h['property_id'])),
            'budget_mtd' => $this->pick($budget[$h['property_id']]['mtd'] ?? null),
            'spark' => $trend($h['property_id']),
        ])->values()->all();
    }

    /** @return array<string, mixed>|null */
    private function pick(?array $row): ?array
    {
        return $row === null ? null : array_intersect_key($row, array_flip(['occupancy_pct', 'adr', 'revpar', 'trevpar', 'total_revenue', 'room_revenue']));
    }
}
