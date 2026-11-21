<?php

namespace App\Http\Controllers;

use App\Models\GuestLedgerLine;
use App\Models\LedgerBalance;
use App\Models\Property;
use App\Support\HotelScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Front-office ledger balances (guest / AR / deposit) from night-audit trial balances,
 * and guest folios from guest trial balances (what each guest owes, unpaid check-outs).
 */
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
            'folios' => $date === null ? null : $this->folios($propertyIds, $date),
        ]);
    }

    /**
     * Guest trial balance for the date: totals, per hotel, and the folios an owner acts on
     * (checked out but still owing, then the largest balances). Guest names are personal
     * data: this page already requires "view financials".
     *
     * @return array<string, mixed>|null
     */
    private function folios($propertyIds, string $date): ?array
    {
        $lines = GuestLedgerLine::with('property:id,name,name_ar')->whereIn('property_id', $propertyIds)->whereDate('business_date', $date)->get();
        if ($lines->isEmpty()) {
            return null;
        }

        $owing = fn ($l) => (float) $l->closing_balance > 0.005;
        $credit = fn ($l) => (float) $l->closing_balance < -0.005;
        $unpaid = fn ($l) => $l->status === 'checked_out' && $owing($l);
        $sum = fn ($c) => round($c->sum(fn ($l) => (float) $l->closing_balance), 2);

        $list = $lines->filter(fn ($l) => $unpaid($l) || abs((float) $l->closing_balance) > 0.005)
            ->sortBy([fn ($a, $b) => $unpaid($b) <=> $unpaid($a), fn ($a, $b) => abs((float) $b->closing_balance) <=> abs((float) $a->closing_balance)])
            ->take(30)
            ->map(fn ($l) => [
                'hotel' => ['id' => $l->property_id, 'name' => $l->property->name, 'name_ar' => $l->property->name_ar],
                'room' => $l->room,
                'guest' => $l->guest_name,
                'arrival' => $l->arrival?->toDateString(),
                'departure' => $l->departure?->toDateString(),
                'charges' => (float) $l->debit,
                'payments' => (float) $l->credit,
                'balance' => (float) $l->closing_balance,
                'status' => $l->status,
                'unpaid' => $unpaid($l),
            ])->values();

        return [
            'balance' => $sum($lines),
            'owing' => ['count' => $lines->filter($owing)->count(), 'amount' => $sum($lines->filter($owing))],
            'credit' => ['count' => $lines->filter($credit)->count(), 'amount' => $sum($lines->filter($credit))],
            'unpaid' => ['count' => $lines->filter($unpaid)->count(), 'amount' => $sum($lines->filter($unpaid))],
            'in_house' => $lines->where('status', 'in_house')->count(),
            'hotels' => $lines->groupBy('property_id')->map(fn ($mine) => [
                'id' => $mine->first()->property_id,
                'name' => $mine->first()->property->name,
                'name_ar' => $mine->first()->property->name_ar,
                'owing' => $sum($mine->filter($owing)),
                'credit' => $sum($mine->filter($credit)),
                'unpaid' => $sum($mine->filter($unpaid)),
                'folios' => $mine->count(),
            ])->sortBy('name')->values(),
            'list' => $list,
        ];
    }
}
