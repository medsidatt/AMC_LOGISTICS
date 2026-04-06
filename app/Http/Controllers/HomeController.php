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

        return Inertia::render('Dashboard', $this->dashboardService->getAdminData());
    }
}
