<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every group page needs a current group the user belongs to. A stale or
 * missing current_group_id falls back to the user's first group; a user with
 * no group at all is signed out.
 */
class EnsureCurrentGroup
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->membership() === null) {
            $first = $user->groups()->orderBy('name')->first();

            if ($first === null) {
                Auth::logout();
                $request->session()->invalidate();

                return redirect('/login')->withErrors(['email' => __('Your account is not linked to a hotel group yet.')]);
            }

            $user->forceFill(['current_group_id' => $first->id])->save();
            $user->load('groups');
        }

        return $next($request);
    }
}
