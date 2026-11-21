<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Forgot password" and setting a password from a link (password reset, or the invitation
 * of a new user: broker "invites", marked with ?invite=1 on the link).
 */
class PasswordController extends Controller
{
    public function request(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::broker()->sendResetLink($request->only('email'));

        // Same answer whether or not the address has an account (no account discovery).
        return back()->with('status', __('If an account exists for this address, a reset link is on its way.'));
    }

    public function edit(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
            'invite' => $request->boolean('invite'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
            'invite' => ['boolean'],
        ]);

        $broker = Password::broker($request->boolean('invite') ? 'invites' : null);
        $status = $broker->reset(
            ['email' => $data['email'], 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token']],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60), 'email_verified_at' => $user->email_verified_at ?? now()])->save();
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => __('This link is no longer valid. Ask for a new one.')]);
        }

        $user = User::where('email', $data['email'])->first();
        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect('/')->with('status', __('Your password is set. Welcome to FGRE.'));
    }
}
