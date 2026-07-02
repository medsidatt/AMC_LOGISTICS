<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\CommandCenters\ExecutiveBusinessCommandCenter;
use App\Domain\Analytics\CommandCenters\FleetBusinessCommandCenter;
use App\Domain\Analytics\CommandCenters\OperationsBusinessCommandCenter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HTTP boundary for the Business Intelligence dashboards. Each action invokes its BI Command
 * Center and returns the response — nothing else. No queries, no calculation, no shaping.
 */
class BusinessDashboardController extends Controller
{
    public function __construct(
        private readonly ExecutiveBusinessCommandCenter $executive,
        private readonly OperationsBusinessCommandCenter $operations,
        private readonly FleetBusinessCommandCenter $fleet,
    ) {
        $this->middleware('auth');
    }

    public function executive(): Response
    {
        return Inertia::render('business/executive/Index', $this->executive->dashboard()->toArray());
    }

    public function operations(): Response
    {
        return Inertia::render('business/operations/Index', $this->operations->dashboard()->toArray());
    }

    public function fleet(): Response
    {
        return Inertia::render('business/fleet/Index', $this->fleet->dashboard()->toArray());
    }
}
