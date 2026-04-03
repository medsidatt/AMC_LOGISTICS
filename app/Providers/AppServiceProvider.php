<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Azure\Provider as AzureProvider;
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

        // Manually register the 'azure' driver
        Socialite::extend('azure', function ($app) {
            $config = $app['config']['services.azure'];
            return Socialite::buildProvider(AzureProvider::class, $config);
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

        // update total leave from the hire date each month 2 days

    }
}
