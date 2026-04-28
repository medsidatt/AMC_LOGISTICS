<?php

namespace App\Http\Controllers;

use App\Services\DashboardDataService;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function __construct(
        private DashboardDataService $dashboardService,
    ) {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = auth()->user();

        if ($user->hasRole('Driver')) {
            return Inertia::render('DriverDashboard', $this->dashboardService->getDriverData($user));
        }

        if ($user->hasRole('HSE Agent') && ! $user->hasRole('Admin') && ! $user->hasRole('Super Admin')) {
            return Inertia::render('HseDashboard', $this->dashboardService->getHseData($user));
        }

        if ($user->hasRole('Logistics Responsible') && ! $user->hasRole('Admin') && ! $user->hasRole('Super Admin')) {
            return Inertia::render('LogisticsResponsibleDashboard', $this->dashboardService->getLogisticsResponsibleData());
        }

        return Inertia::render('Dashboard', $this->dashboardService->getAdminData());
    }
}
