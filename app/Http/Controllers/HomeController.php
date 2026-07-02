<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\Home\HomeDashboardDataProvider;
use App\Domain\Analytics\Metrics\ReportingPeriod;
use App\Http\Controllers\Concerns\ResolvesPeriod;
use App\Services\DashboardDataService;
use App\Services\FleetKpiService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeController extends Controller
{
    use ResolvesPeriod;

    public function __construct(
        private DashboardDataService $dashboardService,
        private FleetKpiService $kpiService,
        private HomeDashboardDataProvider $homeDashboard,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->hasRole('Driver') && ! $user->hasRole('Admin') && ! $user->hasRole('Super Admin')) {
            return Inertia::render('DriverDashboard', $this->dashboardService->getDriverData($user));
        }

        if ($user->hasRole('HSE Agent') && ! $user->hasRole('Admin') && ! $user->hasRole('Super Admin')) {
            return Inertia::render('HseDashboard', $this->dashboardService->getHseData($user));
        }

        if ($user->hasRole('Logistics Responsible') && ! $user->hasRole('Admin') && ! $user->hasRole('Super Admin')) {
            return Inertia::render('LogisticsResponsibleDashboard', $this->dashboardService->getLogisticsResponsibleData($user));
        }

        [$from, $to, $preset] = $this->resolvePeriod($request);

        // Legacy source retained (additive) for the widgets whose metrics have no BI owner yet
        // (production-target %, fuel yield, rankings, discipline) — see dashboard-migration-inventory.md.
        $kpis = $this->kpiService->compute($from, $to);

        // Migrated headline KPIs: sourced from the owning BI metric calculators for the SAME period.
        $period = new ReportingPeriod(
            CarbonImmutable::parse($from->toDateString())->startOfDay(),
            CarbonImmutable::parse($to->toDateString())->endOfDay(),
        );
        $businessKpis = $this->homeDashboard->headline($period);

        return Inertia::render('Dashboard', array_merge(
            $this->dashboardService->getAdminData(),
            [
                'kpi' => $kpis,
                'businessKpis' => $businessKpis,
                'filter' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                    'preset' => $preset,
                ],
            ],
        ));
    }

}
