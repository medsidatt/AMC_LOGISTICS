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

        // R3.0: Read Model extension — the missing detection projections that the R3.1
        // Business Event Derivers consume. One aggregate → one Read Model: Maintenance,
        // Inspection, and Dispatch are new aggregate owners; per-load weights fold into the
        // TransportTracking Read Model (loads) and missing tickets into Dispatch
        // (expected_transport_tickets are dispatch-born children). Sole DB readers; raw values.
        $this->app->bind(
            \App\Domain\Operations\Contracts\MaintenanceReadModelInterface::class,
            \App\Domain\Operations\ReadModels\MaintenanceReadModel::class,
        );
        $this->app->bind(
            \App\Domain\Operations\Contracts\InspectionReadModelInterface::class,
            \App\Domain\Operations\ReadModels\InspectionReadModel::class,
        );
        $this->app->bind(
            \App\Domain\Operations\Contracts\DispatchReadModelInterface::class,
            \App\Domain\Operations\ReadModels\DispatchReadModel::class,
        );
        $this->app->bind(
            \App\Domain\Operations\Contracts\FuelReadModelInterface::class,
            \App\Domain\Operations\ReadModels\FuelReadModel::class,
        );
        $this->app->bind(
            \App\Domain\Operations\Contracts\FleetiConsumptionReadModelInterface::class,
            \App\Domain\Operations\ReadModels\FleetiConsumptionReadModel::class,
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

        // R1.3 inc5 (final calculators): dispatch, objective, inspection, finance.
        $this->app->bind(
            \App\Domain\Operations\Contracts\DispatchCalculatorInterface::class,
            \App\Domain\Operations\Calculations\DispatchCalculator::class,
        );
        $this->app->bind(
            \App\Domain\Operations\Contracts\ObjectiveCalculatorInterface::class,
            \App\Domain\Operations\Calculations\ObjectiveCalculator::class,
        );
        $this->app->bind(
            \App\Domain\Operations\Contracts\InspectionCalculatorInterface::class,
            \App\Domain\Operations\Calculations\InspectionCalculator::class,
        );
        $this->app->bind(
            \App\Domain\Operations\Contracts\BillingCalculatorInterface::class,
            \App\Domain\Operations\Calculations\BillingCalculator::class,
        );

        // R1.6: Operational Intelligence (L5) — decision engine over Events + KPI Registry.
        $this->app->bind(
            \App\Domain\Operations\Intelligence\Contracts\OperationalIntelligenceInterface::class,
            \App\Domain\Operations\Intelligence\OperationalIntelligence::class,
        );

        // R1.7: Dashboard Translators (L6) — presentation translation. Seven concrete
        // translators share DashboardTranslatorInterface, one per command center, and each
        // is a zero-dependency pure transform (conclusions in → immutable view out). There
        // is no single interface→concrete binding: the command center (R2) selects its own
        // translator by identity. Being dependency-free, every translator autowires as-is;
        // no explicit binding is required here.

        // R3.2.1: the derivation-context factory owns the clock + reporting period (single
        // responsibility); the event source no longer creates the context.
        $this->app->bind(
            \App\Domain\Operations\Events\Derivers\Contracts\DerivationContextFactory::class,
            \App\Domain\Operations\Events\Derivers\ClockDerivationContextFactory::class,
        );

        // R3.2: the real Business Event source — the single producer of events. A pure
        // orchestrator: it takes one context from the factory and invokes every deriver once,
        // in this stable order, then merges + de-duplicates. Replaced PendingBusinessEventSource.
        $this->app->bind(
            \App\Domain\Operations\CommandCenters\Contracts\BusinessEventSource::class,
            fn ($app) => new \App\Domain\Operations\CommandCenters\DerivedBusinessEventSource(
                $app->make(\App\Domain\Operations\Events\Derivers\Contracts\DerivationContextFactory::class),
                [
                    $app->make(\App\Domain\Operations\Events\Derivers\MaintenanceEventDeriver::class),
                    $app->make(\App\Domain\Operations\Events\Derivers\InspectionEventDeriver::class),
                    $app->make(\App\Domain\Operations\Events\Derivers\TransportTrackingEventDeriver::class),
                    $app->make(\App\Domain\Operations\Events\Derivers\DispatchEventDeriver::class),
                ],
            ),
        );
        $this->app->bind(
            \App\Domain\Operations\CommandCenters\Contracts\ExecutiveCommandCenterInterface::class,
            \App\Domain\Operations\CommandCenters\Executive\ExecutiveCommandCenter::class,
        );

        // R2.2: Operations Command Center — same orchestration architecture as Executive,
        // reusing the R1.7 OperationsTranslator and the shared BusinessEventSource above.
        $this->app->bind(
            \App\Domain\Operations\CommandCenters\Contracts\OperationsCommandCenterInterface::class,
            \App\Domain\Operations\CommandCenters\Operations\OperationsCommandCenter::class,
        );

        // R4.5: Business Intelligence Command Centers — orchestrate the BI pipeline (registry
        // → KPI calculators → trend calculator → report translator). Each is built with the
        // three R4.2 family calculators (any KPI routes to its supporter) and its own R4.4
        // report translator.
        $businessCalculators = fn ($app): array => [
            $app->make(\App\Domain\Analytics\Metrics\FleetMetricsCalculator::class),
            $app->make(\App\Domain\Analytics\Metrics\OperationsMetricsCalculator::class),
            $app->make(\App\Domain\Analytics\Metrics\ProductivityMetricsCalculator::class),
        ];
        $this->app->bind(
            \App\Domain\Analytics\CommandCenters\ExecutiveBusinessCommandCenter::class,
            fn ($app) => new \App\Domain\Analytics\CommandCenters\ExecutiveBusinessCommandCenter(
                $app->make(\App\Domain\Analytics\Registry\BusinessKpiRegistry::class),
                $businessCalculators($app),
                $app->make(\App\Domain\Analytics\Trends\MovementTrendCalculator::class),
                $app->make(\App\Domain\Analytics\Reports\ExecutiveReportTranslator::class),
            ),
        );
        $this->app->bind(
            \App\Domain\Analytics\CommandCenters\OperationsBusinessCommandCenter::class,
            fn ($app) => new \App\Domain\Analytics\CommandCenters\OperationsBusinessCommandCenter(
                $app->make(\App\Domain\Analytics\Registry\BusinessKpiRegistry::class),
                $businessCalculators($app),
                $app->make(\App\Domain\Analytics\Trends\MovementTrendCalculator::class),
                $app->make(\App\Domain\Analytics\Reports\OperationsReportTranslator::class),
            ),
        );
        $this->app->bind(
            \App\Domain\Analytics\CommandCenters\FleetBusinessCommandCenter::class,
            fn ($app) => new \App\Domain\Analytics\CommandCenters\FleetBusinessCommandCenter(
                $app->make(\App\Domain\Analytics\Registry\BusinessKpiRegistry::class),
                $businessCalculators($app),
                $app->make(\App\Domain\Analytics\Trends\MovementTrendCalculator::class),
                $app->make(\App\Domain\Analytics\Reports\FleetReportTranslator::class),
            ),
        );

        // R5.1: Export engine resolver — routes a format to the one engine that serializes it.
        $this->app->bind(
            \App\Domain\Analytics\Exports\ExportEngineResolver::class,
            fn ($app) => new \App\Domain\Analytics\Exports\ExportEngineResolver([
                $app->make(\App\Domain\Analytics\Exports\HtmlExportEngine::class),
                $app->make(\App\Domain\Analytics\Exports\CsvExportEngine::class),
                $app->make(\App\Domain\Analytics\Exports\JsonExportEngine::class),
            ]),
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
