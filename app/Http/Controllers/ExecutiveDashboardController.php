<?php

namespace App\Http\Controllers;

use App\Domain\Operations\CommandCenters\Contracts\ExecutiveCommandCenterInterface;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HTTP boundary for the Executive Command Center. It invokes the Command Center and returns
 * its response — nothing else. No queries, no calculation, no data shaping: the Command
 * Center already produced a presentation-ready response.
 */
class ExecutiveDashboardController extends Controller
{
    public function __construct(
        private readonly ExecutiveCommandCenterInterface $commandCenter,
    ) {
        $this->middleware('auth');
    }

    public function index(): Response
    {
        return Inertia::render('executive/Index', $this->commandCenter->dashboard()->toArray());
    }
}
