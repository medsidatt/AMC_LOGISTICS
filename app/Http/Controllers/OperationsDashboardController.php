<?php

namespace App\Http\Controllers;

use App\Domain\Operations\CommandCenters\Contracts\OperationsCommandCenterInterface;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HTTP boundary for the Operations Command Center. It invokes the Command Center and returns
 * its response — nothing else. No queries, no calculation, no data shaping: the Command
 * Center already produced a presentation-ready response.
 */
class OperationsDashboardController extends Controller
{
    public function __construct(
        private readonly OperationsCommandCenterInterface $commandCenter,
    ) {
        $this->middleware('auth');
    }

    public function index(): Response
    {
        return Inertia::render('operations/CommandCenter', $this->commandCenter->dashboard()->toArray());
    }
}
