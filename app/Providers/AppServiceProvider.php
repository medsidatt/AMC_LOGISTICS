<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Azure\Provider as AzureProvider;
use SocialiteProviders\Manager\Config as SocialiteConfig;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // SocialiteProviders\Manager is deferred via its parent and never boots
        // for HTTP requests, so register the `azure` driver directly here and
        // pass services.azure (incl. tenant) through setConfig so the provider
        // hits https://login.microsoftonline.com/<tenant>/oauth2/v2.0/...
        Socialite::extend('azure', function ($app) {
            $config = $app['config']['services.azure'];
            $provider = Socialite::buildProvider(AzureProvider::class, $config);
            $provider->setConfig(new SocialiteConfig(
                $config['client_id'],
                $config['client_secret'],
                $config['redirect'],
                $config,
            ));
            return $provider;
        });

        Carbon::setLocale('fr');

        $formattedDate = strtoupper(Carbon::now()->translatedFormat('F Y'));

        Blueprint::macro('monthYear', function ($column) use ($formattedDate) {
            $this->string($column)->default($formattedDate);
        });

        Blueprint::macro('userActions', function () {
            $this->foreignId('created_by')->nullable()->constrained('users');
            $this->foreignId('updated_by')->nullable()->constrained('users');
            $this->foreignId('deleted_by')->nullable()->constrained('users');
        });

        Blade::if('hasAnyPermission', function ($permissions) {
            if (is_string($permissions)) {
                $permissions = explode('|', $permissions);
            }
            foreach ($permissions as $permission) {
                if (auth()->check() && auth()->user()->can($permission)) {
                    return true;
                }
            }
            return false;
        });

        Route::macro('ajaxCreate', function ($uri, $action) {
            return Route::get($uri, $action)->middleware('ajax');
        });

        Route::macro('ajaxEdit', function ($uri, $action) {
            return Route::get($uri, $action)->middleware('ajax');
        });

        Route::macro('ajaxGet', function ($uri, $action) {
            return Route::get($uri, $action)->middleware('ajax');
        });

        // Rate limiting for API
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
