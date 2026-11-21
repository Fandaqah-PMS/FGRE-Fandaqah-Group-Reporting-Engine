<?php

namespace App\Http\Controllers;

use App\Models\LedgerBalance;
use App\Models\Property;
use App\Support\HotelScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Front-office ledger balances (guest / AR / deposit) from night-audit trial balances. */
class LedgersController extends Controller
{
    public const LEDGERS = ['guest', 'ar', 'deposit'];

    public function __invoke(Request $request): Response
    {
        Gate::authorize('view financials');

        $propertyIds = HotelScope::propertyIds($request);

        $dates = LedgerBalance::whereIn('property_id', $propertyIds)
            ->select('business_date')->distinct()->orderByDesc('business_date')->limit(31)->pluck('business_date')
            ->map(fn ($d) => substr((string) $d, 0, 10))->values();
        $date = in_array($this->dateParam($request), $dates->all(), true) ? $this->dateParam($request) : $dates->first();

        $balances = $date === null ? collect() : LedgerBalance::whereIn('property_id', $propertyIds)->whereDate('business_date', $date)->get();
        $hotels = Property::whereIn('id', $balances->pluck('property_id')->unique())->orderBy('name')->get(['id', 'name', 'name_ar']);

        $rows = $hotels->map(function (Property $h) use ($balances) {
            $mine = $balances->where('property_id', $h->id)->keyBy('ledger');

            return [
                'id' => $h->id, 'name' => $h->name, 'name_ar' => $h->name_ar,
                ...collect(self::LEDGERS)->mapWithKeys(fn ($l) => [$l => isset($mine[$l]) ? (float) $mine[$l]->closing_balance : null])->all(),
                'opening' => collect(self::LEDGERS)->mapWithKeys(fn ($l) => [$l => isset($mine[$l]) ? (float) $mine[$l]->opening_balance : null])->all(),
            ];
        })->values();

        return Inertia::render('Ledgers', [
            'dates' => $dates,
            'date' => $date,
            'rows' => $rows,
            'totals' => collect(self::LEDGERS)->mapWithKeys(fn ($l) => [$l => round($rows->sum($l), 2)]),
        ]);
    }
}
