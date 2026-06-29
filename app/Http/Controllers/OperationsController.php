<?php

namespace App\Http\Controllers;

use App\Models\DailyChecklistIssue;
use App\Models\ExpectedTransportTicket;
use App\Services\DispatchWorkspaceService;
use App\Services\PlanningWorkspaceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Operations — flat navigation: one route per workflow, one page each. No
 * workspace shell, no tabs. Each action renders a single workflow page reusing
 * the existing services/panels; this controller owns no business logic.
 *
 *   /operations     → redirect to /planning (legacy cockpit, retired)
 *   /planning       → objectives + availability + calendar
 *   /dispatch       → daily dispatch board
 *   /assignments    → crew roster
 *   /reconciliation → missing-ticket worklist
 *   /exceptions     → operational issues inbox
 */
class OperationsController extends Controller
{
    public function __construct(
        private readonly DispatchWorkspaceService $dispatch,
        private readonly PlanningWorkspaceService $planning,
    ) {
        $this->middleware('auth');
    }

    /**
     * Legacy cockpit URL → canonical Planning workflow. The workspace/tab concept is
     * retired (flat one-route-per-workflow nav); kept as a redirect for one release.
     */
    public function center(): RedirectResponse
    {
        return redirect('/planning');
    }

    /** Planning Command Center — operational briefing (cheap; no per-truck load). */
    public function planning(): Response
    {
        return Inertia::render('operations/planning/Overview', $this->planning->commandCenterData());
    }

    /** Manage Objectives — full list (drill-in from the overview). */
    public function planningObjectives(): Response
    {
        return Inertia::render('operations/planning/Objectives', $this->planning->objectivesData(false));
    }

    /** Manage Availability — heavy per-truck data, loaded only here. */
    public function planningAvailability(Request $request): Response
    {
        $anchor = $request->query('month') ? Carbon::parse($request->query('month')) : Carbon::now();

        return Inertia::render('operations/planning/Availability', $this->planning->availabilityData($anchor));
    }

    /** Manage Calendar — working days + exceptions. */
    public function planningCalendar(): Response
    {
        return Inertia::render('operations/planning/Calendar', $this->planning->calendarData());
    }

    public function dispatch(Request $request): Response
    {
        $date = $request->query('date') ? Carbon::parse($request->query('date')) : Carbon::tomorrow();

        return Inertia::render('operations/Dispatch', $this->dispatch->programData($date));
    }

    public function crew(): Response
    {
        return Inertia::render('operations/Assignments', $this->dispatch->crewData());
    }

    public function reconciliation(): Response
    {
        return Inertia::render('operations/Reconciliation', $this->reconciliationData());
    }

    public function exceptions(): Response
    {
        return Inertia::render('operations/Exceptions', ['items' => $this->exceptionsList(50)]);
    }

    // ---- shared data assembly (reused; no business logic) ----

    /** Reconciliation rows — same read as TicketGapController::index (reused). */
    private function reconciliationData(): array
    {
        $rows = ExpectedTransportTicket::query()
            ->with([
                'truck:id,matricule',
                'provider:id,name',
                'dispatch:id,dispatch_date,driver_id',
                'dispatch.driver:id,name',
                'transportTracking:id,reference',
            ])
            ->whereIn('status', [
                ExpectedTransportTicket::STATUS_EXPECTED,
                ExpectedTransportTicket::STATUS_MISSING,
                ExpectedTransportTicket::STATUS_MATCHED,
            ])
            ->orderByDesc('loaded_at')
            ->limit(500)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'status' => $r->status,
                'loaded_at' => optional($r->loaded_at)?->toIso8601String(),
                'left_at' => optional($r->left_at)?->toIso8601String(),
                'deadline_at' => optional($r->deadline_at)?->toIso8601String(),
                'provider' => $r->provider ? ['id' => $r->provider->id, 'name' => $r->provider->name] : null,
                'truck' => $r->truck ? ['id' => $r->truck->id, 'matricule' => $r->truck->matricule] : null,
                'driver' => $r->dispatch?->driver ? ['id' => $r->dispatch->driver->id, 'name' => $r->dispatch->driver->name] : null,
                'dispatch_date' => optional($r->dispatch?->dispatch_date)?->toDateString(),
                'tracking' => $r->transportTracking ? [
                    'id' => $r->transportTracking->id,
                    'reference' => $r->transportTracking->reference,
                ] : null,
            ]);

        return [
            'rows' => $rows,
            'counts' => [
                'expected' => ExpectedTransportTicket::query()->status(ExpectedTransportTicket::STATUS_EXPECTED)->count(),
                'missing' => ExpectedTransportTicket::query()->status(ExpectedTransportTicket::STATUS_MISSING)->count(),
                'matched' => ExpectedTransportTicket::query()->status(ExpectedTransportTicket::STATUS_MATCHED)->count(),
            ],
        ];
    }

    /** Unified exception worklist (missing tickets + flagged checklist issues). */
    private function exceptionsList(int $limit): array
    {
        $missing = ExpectedTransportTicket::query()
            ->status(ExpectedTransportTicket::STATUS_MISSING)
            ->with(['truck:id,matricule', 'provider:id,name'])
            ->orderByDesc('loaded_at')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'key' => 'missing-'.$r->id,
                'type' => 'missing_ticket',
                'severity' => 'high',
                'title' => 'Ticket manquant — '.($r->truck?->matricule ?? '—'),
                'subtitle' => $r->provider?->name ?? '—',
                'at' => optional($r->loaded_at)?->toIso8601String(),
                'link' => ($r->truck && $r->provider)
                    ? '/transport_tracking?create=1&truck_id='.$r->truck->id.'&provider_id='.$r->provider->id.'&provider_date='.(optional($r->loaded_at)?->toDateString() ?? '')
                    : '/reconciliation',
            ]);

        $issues = DailyChecklistIssue::query()
            ->where('flagged', true)
            ->whereNull('resolved_at')
            ->with(['truck:id,matricule', 'driver:id,name', 'dailyChecklist.truck:id,matricule', 'dailyChecklist.driver:id,name'])
            ->orderByDesc('reported_at')
            ->limit($limit)
            ->get()
            ->map(fn (DailyChecklistIssue $i) => [
                'key' => 'issue-'.$i->id,
                'type' => 'checklist_issue',
                'severity' => $i->severity ?? 'medium',
                'title' => 'Problème checklist — '.($i->truck?->matricule ?? $i->dailyChecklist?->truck?->matricule ?? '—'),
                'subtitle' => $i->category ?? ($i->driver?->name ?? $i->dailyChecklist?->driver?->name ?? '—'),
                'at' => optional($i->reported_at)?->toIso8601String(),
                'link' => '/logistics/validation/checklists',
            ]);

        return $missing->concat($issues)
            ->sortByDesc('at')
            ->take($limit)
            ->values()
            ->all();
    }
}
