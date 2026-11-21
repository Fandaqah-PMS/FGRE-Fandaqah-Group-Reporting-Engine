<?php

namespace App\Http\Controllers;

use App\Models\LedgerBalance;
use App\Models\Property;
use App\Models\TrialBalanceLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consolidated general-ledger trial balance (accounts × hotels) and the
 * front-office ledger balances, for the hotels the user may see.
 */
class FinancialsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('view financials');

        $propertyIds = $request->user()->accessiblePropertyIds();
        $properties = Property::whereIn('id', $propertyIds)->orderBy('name')->get(['id', 'name', 'name_ar', 'currency']);

        $dates = TrialBalanceLine::whereIn('property_id', $propertyIds)
            ->select('business_date')->distinct()->orderByDesc('business_date')->limit(24)->pluck('business_date')
            ->map(fn ($d) => substr((string) $d, 0, 10))->values();

        $date = in_array($request->query('date'), $dates->all(), true) ? $request->query('date') : $dates->first();

        $lines = $date === null ? collect() : TrialBalanceLine::whereIn('property_id', $propertyIds)->whereDate('business_date', $date)->get();

        // One row per standard account number; hotels as columns; signed closing balances.
        $accounts = $lines->groupBy('account_code')->sortKeys()->map(function ($rows, $code) {
            $byHotel = $rows->groupBy('property_id')->map(fn ($r) => $r->sum(fn ($l) => (float) $l->closing_balance));

            return [
                'code' => (string) $code,
                'name' => $rows->first()->account_name,
                'class' => substr((string) $code, 0, 1),
                'hotels' => $byHotel,
                'total' => round($byHotel->sum(), 2),
                'debit' => round($rows->sum(fn ($l) => (float) $l->debit), 2),
                'credit' => round($rows->sum(fn ($l) => (float) $l->credit), 2),
            ];
        })->values();

        $periodStart = $lines->first()?->period_start?->toDateString();

        $ledgerDate = LedgerBalance::whereIn('property_id', $propertyIds)->max('business_date');
        $ledgers = $ledgerDate === null ? collect() : LedgerBalance::whereIn('property_id', $propertyIds)
            ->whereDate('business_date', substr((string) $ledgerDate, 0, 10))->get()
            ->groupBy('property_id')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($l) => [$l->ledger => (float) $l->closing_balance]));

        return Inertia::render('Financials', [
            'dates' => $dates,
            'date' => $date,
            'periodStart' => $periodStart,
            'hotels' => $properties,
            'accounts' => $accounts,
            'ledgerDate' => $ledgerDate === null ? null : substr((string) $ledgerDate, 0, 10),
            'ledgers' => $ledgers,
        ]);
    }
}
