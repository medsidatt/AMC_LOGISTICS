<?php

namespace App\Http\Middleware;

use App\Support\SilentSso;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Hard-stops any authenticated request made by a suspended account.
 *
 * Suspension is enforced server-side here (not in the UI) so it covers every
 * authenticated entry point at once: form login, Microsoft OAuth, and reuse of
 * an existing session created before the account was suspended. The account is
 * logged out and the session destroyed on the spot.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->is_suspended) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Keep silent SSO from instantly re-authenticating the suspended
            // account via its still-active Microsoft browser session. The
            // OAuth callback also rejects suspended accounts as a backstop.
            SilentSso::markFailed($request);
            SilentSso::markLoggedOut($request);

            $message = 'Votre compte a été suspendu. Contactez l\'administrateur.';

            if ($request->expectsJson()) {
                abort(403, $message);
            }

            return redirect('/login')->with('error', $message);
        }

        return $next($request);
    }
}
