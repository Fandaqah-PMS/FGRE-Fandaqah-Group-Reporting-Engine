<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Support\HotelScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Switch between "all hotels" (consolidated) and one hotel. */
class ScopeController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $hotel = $request->input('hotel');

        if ($hotel === null || $hotel === '' || $hotel === 'all') {
            $request->session()->forget(HotelScope::key($request));

            return back();
        }

        abort_unless($request->user()->accessiblePropertyIds()->contains((int) $hotel), 404);
        $request->session()->put(HotelScope::key($request), (int) $hotel);

        return back();
    }

    /** Link target for "open this hotel": select it, then show the dashboard. */
    public function show(Request $request, Property $property): RedirectResponse
    {
        abort_unless($request->user()->accessiblePropertyIds()->contains($property->id), 404);
        $request->session()->put(HotelScope::key($request), $property->id);

        return redirect()->route('dashboard', array_filter(['date' => $this->dateParam($request)]));
    }
}
