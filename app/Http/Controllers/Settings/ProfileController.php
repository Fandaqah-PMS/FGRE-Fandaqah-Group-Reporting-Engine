<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ReportSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** The signed-in user's own settings: name, email, language, password. */
class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Settings/Profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale ?: 'ar',
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
            'memberships' => $user->groups->map(fn ($g) => [
                'id' => $g->id, 'name' => $g->name, 'name_ar' => $g->name_ar, 'role' => $g->pivot->role,
                'hotels' => $g->pivot->all_properties ? null : $user->accessiblePropertyIds($g->id)->count(),
            ])->values(),
            'subscriptionCount' => ReportSubscription::where('user_id', $user->id)->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'locale' => ['required', Rule::in(['ar', 'en'])],
            // Changing the sign-in address needs the current password.
            'current_password' => [Rule::requiredIf(fn () => mb_strtolower($request->input('email')) !== mb_strtolower($user->email)), 'nullable', 'current_password'],
        ]);

        $user->update(['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'locale' => $data['locale']]);
        $request->session()->put('locale', $data['locale']);

        return back()->with('status', __('Profile saved.'));
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $request->user()->update(['password' => $data['password']]);
        // Other browsers signed in with the old password are signed out.
        Auth::logoutOtherDevices($data['password']);

        return back()->with('status', __('Password changed.'));
    }
}
