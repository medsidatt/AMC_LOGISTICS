<?php

namespace App\Console\Commands;

use App\Services\TicketReconciliationService;
use Illuminate\Console\Command;

/**
 * Nightly reconciliation: every GPS-observed quarry loading
 * (ExpectedTransportTicket) is matched to a TransportTracking ticket or
 * flagged as missing once the deadline passes. Closes the under-ticketing
 * gap (e.g. March 2026: 34 GPS CSE visits vs 13 CSE tickets).
 */
class ReconcileExpectedTickets extends Command
{
    protected $signature = 'logistics:reconcile-expected-tickets';

    protected $description = 'Reconcile GPS quarry loadings against TransportTracking tickets.';

    public function __construct(private readonly TicketReconciliationService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $stats = $this->service->reconcileAll();

        $this->table(['Outcome', 'Count'], [
            ['Matched', $stats['matched'] ?? 0],
            ['Missing (past deadline)', $stats['missing'] ?? 0],
            ['Still expected (within deadline)', $stats['kept_expected'] ?? 0],
        ]);

        return self::SUCCESS;
    }
}
