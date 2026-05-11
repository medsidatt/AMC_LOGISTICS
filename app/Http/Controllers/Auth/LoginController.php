<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\SilentSso;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    protected $redirectTo = '/dashboard';

    public function showLoginForm(Request $request)
    {
        // Try silent Microsoft SSO if we haven't recently failed it. After
        // a failure the helper enforces a short cooldown so the user sees
        // the form without an immediate re-redirect.
        if (SilentSso::shouldAttempt($request) && ! $request->session()->has('error')) {
            return redirect('/auth/microsoft?silent=1');
        }

        return \Inertia\Inertia::render('auth/Login');
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /**
     * Override the default post-login redirect so Drivers always land on
     * /dashboard (their DriverDashboard) instead of a stored "intended"
     * URL they may not have permission for.
     */
    protected function authenticated(\Illuminate\Http\Request $request, $user)
    {
        if ($user->hasRole('Driver')) {
            return redirect('/dashboard');
        }

        return redirect()->intended($this->redirectPath());
    }

    protected function loggedOut(Request $request)
    {
        // Block the auth middleware from immediately re-authenticating the
        // user via silent SSO (their Microsoft browser session is still
        // active). Cleared the next time they explicitly start a sign-in.
        SilentSso::markLoggedOut($request);

        return redirect('/login');
    }
}
