<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\TrialBalanceLine;
use App\Support\HotelScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * General-ledger trial balance, consolidated (accounts × hotels) or for one hotel.
 * Balances are signed: debit +, credit −. Account class = first digit (NTMP / USALI):
 * 1 assets, 2 liabilities, 3 equity, 4 revenue, 5+ expenses.
 */
class TrialBalanceController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('view financials');

        $propertyIds = HotelScope::propertyIds($request);
        $hotels = Property::whereIn('id', $propertyIds)->orderBy('name')->get(['id', 'name', 'name_ar', 'currency']);

        $dates = TrialBalanceLine::whereIn('property_id', $propertyIds)
            ->select('business_date')->distinct()->orderByDesc('business_date')->limit(24)->pluck('business_date')
            ->map(fn ($d) => substr((string) $d, 0, 10))->values();

        $date = in_array($request->query('date'), $dates->all(), true) ? $request->query('date') : $dates->first();
        $lines = $date === null ? collect() : TrialBalanceLine::whereIn('property_id', $propertyIds)->whereDate('business_date', $date)->get();

        $accounts = $lines->groupBy('account_code')->sortKeys()->map(function ($rows, $code) {
            $byHotel = $rows->groupBy('property_id')->map(fn ($r) => round($r->sum(fn ($l) => (float) $l->closing_balance), 2));

            return [
                'code' => (string) $code,
                'name' => $rows->first()->account_name,
                'class' => $this->accountClass((string) $code),
                'hotels' => $byHotel,
                'total' => round($byHotel->sum(), 2),
                'debit' => round($rows->sum(fn ($l) => (float) $l->debit), 2),
                'credit' => round($rows->sum(fn ($l) => (float) $l->credit), 2),
            ];
        })->values();

        $classTotal = fn (string $class, ?int $hotelId = null) => round($accounts->where('class', $class)
            ->sum(fn ($a) => $hotelId === null ? $a['total'] : (float) ($a['hotels'][$hotelId] ?? 0)), 2);

        // Month-by-month trend for the KPI-card sparklines: every trial balance up to this one.
        $trendDates = $dates->filter(fn ($d) => $date !== null && $d <= $date)->take(12)->reverse()->values();
        $trendLines = $trendDates->isEmpty() ? collect() : TrialBalanceLine::whereIn('property_id', $propertyIds)
            ->whereDate('business_date', '>=', $trendDates->first())->whereDate('business_date', '<=', $trendDates->last())
            ->get(['business_date', 'account_code', 'closing_balance'])
            ->groupBy(fn ($l) => substr((string) $l->business_date, 0, 10));
        $trend = $trendDates->map(function ($d) use ($trendLines) {
            $sum = fn (string $class) => round(($trendLines[$d] ?? collect())
                ->filter(fn ($l) => $this->accountClass((string) $l->account_code) === $class)->sum(fn ($l) => (float) $l->closing_balance), 2);
            $revenue = -$sum('revenue');
            $expenses = $sum('expenses');

            return ['date' => $d, 'revenue' => $revenue, 'expenses' => $expenses, 'result' => round($revenue - $expenses, 2)];
        })->values();

        return Inertia::render('TrialBalance', [
            'trend' => $trend,
            'dates' => $dates,
            'date' => $date,
            'periodStart' => $lines->first()?->period_start?->toDateString(),
            'hotels' => $hotels,
            'accounts' => $accounts,
            'summary' => [
                'revenue' => -$classTotal('revenue'),
                'expenses' => $classTotal('expenses'),
                'assets' => $classTotal('assets'),
                'liabilities' => -$classTotal('liabilities'),
                'net' => round($accounts->sum('total'), 2),
                'byHotel' => $hotels->map(fn ($h) => [
                    'id' => $h->id, 'name' => $h->name, 'name_ar' => $h->name_ar,
                    'revenue' => -$classTotal('revenue', $h->id),
                    'expenses' => $classTotal('expenses', $h->id),
                ])->values(),
            ],
        ]);
    }

    private function accountClass(string $code): string
    {
        return match ($code[0] ?? '') {
            '1' => 'assets',
            '2' => 'liabilities',
            '3' => 'equity',
            '4' => 'revenue',
            '5', '6', '7', '8' => 'expenses',
            default => 'other',
        };
    }
}
