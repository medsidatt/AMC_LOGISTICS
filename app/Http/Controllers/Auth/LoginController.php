<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
        // Try silent Microsoft SSO once per session. If the user is signed in
        // to a tenant account we get them straight in; otherwise the callback
        // bounces back here and we render the form.
        if (! $request->session()->has('sso_attempted') && ! $request->session()->has('error')) {
            $request->session()->put('sso_attempted', true);
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
}
