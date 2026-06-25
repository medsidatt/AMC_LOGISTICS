<?php

namespace App\Http\Controllers;

use App\Models\FleetObjective;
use App\Models\Truck;
use App\Services\FleetCapacityService;
use App\Services\FleetObjectiveService;
use App\Services\PlanningPeriodResolver;
use App\Services\PlanningWorkspaceService;
use App\Services\RotationAchievementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Objectives — the authoring surface for the COMMITMENT only: period + target
 * tonnage (+ optional notes) for WEEK / MONTH / YEAR / CUSTOM. It answers a
 * single question: "what are we committing to transport?".
 *
 * Truck allocation (which trucks work vs rest, per-truck rotations, availability)
 * is a separate workflow owned by Planning — it answers "how will we achieve it?".
 * Saving an objective therefore never depends on allocation: capacity coverage is
 * surfaced as advisory information only, never enforced as a validation rule, so
 * ambitious / under-resourced / stretch targets can always be recorded.
 *
 * Planned-vs-Actual / achievement KPIs live in the Planning Dashboard, not here.
 */
class FleetObjectiveController extends Controller
{
    public function __construct(
        private readonly FleetObjectiveService $objectives,
        private readonly PlanningPeriodResolver $periods,
        private readonly FleetCapacityService $capacity,
        private readonly RotationAchievementService $achievement,
        private readonly PlanningWorkspaceService $planning,
    ) {
        $this->middleware('permission:fleet-roster-plan');
    }

    /**
     * The objective authoring page (create or edit): period + target + notes.
     * Also passes a read-only fleet-capacity estimate so the manager can see
     * whether a target is realistic — advisory only, never a save gate.
     */
    public function create(Request $request)
    {
        $editing = $request->query('objective')
            ? FleetObjective::find($request->query('objective'))
            : null;

        $start = $editing
            ? $editing->start_date->copy()
            : ($request->query('start') ? Carbon::parse($request->query('start')) : Carbon::now()->startOfWeek(Carbon::MONDAY));
        $end = $editing
            ? $editing->end_date->copy()
            : ($request->query('end') ? Carbon::parse($request->query('end')) : $start->copy()->addDays(5));
        $periodType = $editing->period_type ?? FleetObjective::PERIOD_WEEK;
        $targetTons = $editing ? (float) $editing->target_tons : 0.0;

        // Read-only capacity estimate for the advisory summary. Aggregate only —
        // no per-truck allocation is exposed on this page (that lives in Planning).
        $availableTrucks = Truck::query()
            ->where('is_active', true)
            ->where('is_available', true)
            ->get();

        $fleetWeeklyCapacityT = round(
            $availableTrucks->sum(fn (Truck $t) => $this->capacity->truckDailyCapacity($t)['target_weekly_capacity_t']),
            2,
        );

        // Compact previous-period context (read-only): the most recent active
        // objective of the same period type that ended before this one, with its
        // achieved tonnage. Reuses RotationAchievementService — no logic added.
        $previous = FleetObjective::query()
            ->active()
            ->where('period_type', $periodType)
            ->whereDate('end_date', '<', $start->toDateString())
            ->when($editing, fn ($q) => $q->where('id', '!=', $editing->id))
            ->orderByDesc('end_date')
            ->first();

        $previousPeriod = null;
        if ($previous) {
            $ach = $this->achievement->forPeriod(
                $previous->start_date->copy(),
                $previous->end_date->copy(),
                $previous->period_type,
            );
            $previousPeriod = [
                'start' => $previous->start_date->toDateString(),
                'end' => $previous->end_date->toDateString(),
                'target_tons' => round((float) $previous->target_tons, 2),
                'done_tons' => round((float) ($ach['fleet']['done_tons'] ?? 0), 2),
                'pct' => $ach['fleet']['pct'] ?? null,
            ];
        }

        return Inertia::render('logistics/objectives/Create', [
            'editing' => $editing ? ['id' => $editing->id] : null,
            'periodTypes' => PlanningPeriodResolver::MODES,
            'period' => ['type' => $periodType, 'start' => $start->toDateString(), 'end' => $end->toDateString()],
            'targetTons' => $targetTons,
            'notes' => $editing?->notes ?? '',
            'planCapacityT' => round($this->capacity->defaultCapacityTonnage(), 2),
            'fleetWeeklyCapacityT' => $fleetWeeklyCapacityT,
            'availableTruckCount' => $availableTrucks->count(),
            'previousPeriod' => $previousPeriod,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'period_type' => ['required', 'in:' . implode(',', PlanningPeriodResolver::MODES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'target_tons' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $p = $this->periods->resolve($data['period_type'], $data['start_date'], $data['start_date'], $data['end_date'] ?? null);
        $userId = $request->user()?->id;

        // Never silently overwrite an existing manual objective. If one already covers
        // this exact period, bounce back (no write) so the UI can ask the planner to
        // confirm the replacement; `override` is sent once they accept (or when editing
        // a locked period). Manual planning is authoritative — read-only until confirmed.
        $existing = FleetObjective::active()
            ->where('period_type', $p['mode'])
            ->whereDate('start_date', $p['start']->toDateString())
            ->whereDate('end_date', $p['end']->toDateString())
            ->first(['id', 'target_tons']);

        if ($existing && ! $request->boolean('override')) {
            return back()->with('objectiveConflict', [
                'period_type' => $p['mode'],
                'start' => $p['start']->toDateString(),
                'end' => $p['end']->toDateString(),
                'existing_tons' => round((float) $existing->target_tons, 2),
                'new_tons' => round((float) $data['target_tons'], 2),
            ]);
        }

        // Commitment only — no allocation is read or written here. The working set
        // is derived from whatever rest windows Planning has already set (null),
        // defaulting to all available trucks. Saving never depends on coverage.
        DB::transaction(function () use ($p, $userId, $data) {
            $this->objectives->upsert(
                $p['start'],
                $p['end'],
                (float) $data['target_tons'],
                $userId,
                $data['notes'] ?? null,
                null,
                $p['mode'],
            );
        });

        return redirect('/planning')
            ->with('success', 'Objectif enregistré.');
    }

    /** Live parent-allocation context for the objective drawer (JSON). Read-only. */
    public function parentAllocation(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'period_type' => ['required', 'in:' . implode(',', PlanningPeriodResolver::MODES)],
            'start' => ['required', 'date'],
            'end' => ['required', 'date'],
        ]);

        return response()->json(
            $this->planning->parentAllocation($data['period_type'], $data['start'], $data['end'])
        );
    }

    public function archive(Request $request, FleetObjective $objective)
    {
        $objective->archived_at = $objective->archived_at ? null : now();
        $objective->save();

        // back() so it works from the standalone list and the Planning tab alike.
        return back()->with('success', $objective->archived_at ? 'Objectif archivé.' : 'Objectif réactivé.');
    }
}
