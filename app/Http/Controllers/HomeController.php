<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPeriod;
use App\Services\DashboardDataService;
use App\Services\FleetKpiService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeController extends Controller
{
    use ResolvesPeriod;

    public function __construct(
        private DashboardDataService $dashboardService,
        private FleetKpiService $kpiService,
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
        $kpis = $this->kpiService->compute($from, $to);

        return Inertia::render('Dashboard', array_merge(
            $this->dashboardService->getAdminData(),
            [
                'kpi' => $kpis,
                'filter' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                    'preset' => $preset,
                ],
            ],
        ));
    }

}
