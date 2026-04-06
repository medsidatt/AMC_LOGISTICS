<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
//use Socialite;

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

    public function showLoginForm()
    {
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

    public function redirectToAzure()
    {
        return Socialite::driver('mailgun')->redirect();
    }

    public function handleAzureCallback()
    {
        $azureUser = Socialite::driver('microsoft')->user();

        // Find or create the user in your database
        $user = User::firstOrCreate(
            ['email' => $azureUser->getEmail()],
            [
                'name' => $azureUser->getName(),
                'password' => bcrypt('password'), // Random password
            ]
        );

        // Log in the user
        Auth::login($user, true);

        return redirect('/home'); // Redirect to your desired route
    }
}
