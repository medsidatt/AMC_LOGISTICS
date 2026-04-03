<?php

namespace App\Http\Controllers;

use App\Providers\CustomAzureProvider;
use Illuminate\Support\Facades\Auth;
use App\Models\Auth\User;
use Laravel\Socialite\Facades\Socialite;

class MicrosoftAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('azure')
            ->scopes(['openid', 'profile', 'email', 'User.Read'])
            ->redirect();
    }

    public function callback()
    {
        $microsoftUser = Socialite::buildProvider(
            CustomAzureProvider::class,
            [
                'client_id' => config('services.azure.client_id'),
                'client_secret' => config('services.azure.client_secret'),
                'redirect' => config('services.azure.redirect'),
                'tenant' => config('services.azure.tenant'),
            ]
        )->stateless()->user();

        $email = $microsoftUser->email
            ?? $microsoftUser->user['mail']
            ?? $microsoftUser->user['userPrincipalName'];

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $microsoftUser->name]
        );

        Auth::login($user);

        return redirect()->route('home');
    }
}
