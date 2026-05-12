<?php

namespace App\Http\Controllers;

use App\Services\DashboardDataService;
use App\Services\FleetKpiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeController extends Controller
{
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
            return Inertia::render('LogisticsResponsibleDashboard', $this->dashboardService->getLogisticsResponsibleData());
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

    private function resolvePeriod(Request $request): array
    {
        $preset = $request->string('preset')->toString() ?: 'month';
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        if ($from && $to) {
            return [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay(), 'custom'];
        }

        return match ($preset) {
            'day' => [now()->startOfDay(), now()->endOfDay(), 'day'],
            'week' => [now()->startOfWeek(Carbon::MONDAY), now()->endOfDay(), 'week'],
            'year' => [now()->startOfYear(), now()->endOfDay(), 'year'],
            default => [now()->startOfMonth(), now()->endOfDay(), 'month'],
        };
    }
}
