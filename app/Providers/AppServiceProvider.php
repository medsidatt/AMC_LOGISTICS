<?php

namespace App\Providers;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
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
        // Operational Intelligence — Read Models (L0). Bind contracts to projections.
        $this->app->bind(
            \App\Domain\Operations\Contracts\TransportTrackingReadModelInterface::class,
            \App\Domain\Operations\ReadModels\TransportTrackingReadModel::class,
        );
        $this->app->bind(
            \App\Domain\Operations\Contracts\FleetReadModelInterface::class,
            \App\Domain\Operations\ReadModels\FleetReadModel::class,
        );

        // Operational Intelligence — Domain Calculators (L2). First dups: weight, capacity.
        $this->app->bind(
            \App\Domain\Operations\Contracts\WeightCalculatorInterface::class,
            \App\Domain\Operations\Calculations\WeightCalculator::class,
        );
        $this->app->bind(
            \App\Domain\Operations\Contracts\CapacityCalculatorInterface::class,
            \App\Domain\Operations\Calculations\CapacityCalculator::class,
        );

        // R1.3 inc3: rotation, cycle, utilization.
        $this->app->bind(
            \App\Domain\Operations\Contracts\RotationCalculatorInterface::class,
            \App\Domain\Operations\Calculations\RotationCalculator::class,
        );
        $this->app->bind(
            \App\Domain\Operations\Contracts\CycleCalculatorInterface::class,
            \App\Domain\Operations\Calculations\CycleCalculator::class,
        );
        $this->app->bind(
            \App\Domain\Operations\Contracts\UtilizationCalculatorInterface::class,
            \App\Domain\Operations\Calculations\UtilizationCalculator::class,
        );

        // R1.3 inc4: fuel, maintenance, productivity.
        $this->app->bind(
            \App\Domain\Operations\Contracts\FuelCalculatorInterface::class,
            \App\Domain\Operations\Calculations\FuelCalculator::class,
        );
        $this->app->bind(
            \App\Domain\Operations\Contracts\MaintenanceCalculatorInterface::class,
            \App\Domain\Operations\Calculations\MaintenanceCalculator::class,
        );
        $this->app->bind(
            \App\Domain\Operations\Contracts\ProductivityCalculatorInterface::class,
            \App\Domain\Operations\Calculations\ProductivityCalculator::class,
        );
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

        // Single source of truth for password strength across every endpoint
        // that sets or changes a password (self-service, force-change, admin).
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers());

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

        // Audit auth events
        Event::listen(Login::class, function (Login $event) {
            AuditLog::record('login', $event->user instanceof \Illuminate\Database\Eloquent\Model ? $event->user : null);
        });
        Event::listen(Logout::class, function (Logout $event) {
            AuditLog::record('logout', $event->user instanceof \Illuminate\Database\Eloquent\Model ? $event->user : null);
        });
        Event::listen(Failed::class, function (Failed $event) {
            AuditLog::record('login_failed', null, null, [
                'actor_name' => $event->credentials['email'] ?? 'unknown',
            ]);
        });
    }
}
