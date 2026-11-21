<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Models\HotelGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreferencesController extends Controller
{
    public function switchGroup(Request $request, HotelGroup $group): RedirectResponse
    {
        abort_unless($request->user()->membership($group->id) !== null, 404);

        $request->user()->switchGroup($group);

        return redirect('/');
    }

    public function locale(Request $request): RedirectResponse
    {
        $locale = $request->validate(['locale' => ['required', Rule::in(SetLocale::LOCALES)]])['locale'];

        $request->session()->put('locale', $locale);
        $request->user()?->forceFill(['locale' => $locale])->save();

        return back();
    }
}
