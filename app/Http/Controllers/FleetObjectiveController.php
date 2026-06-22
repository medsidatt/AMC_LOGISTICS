<?php

namespace App\Http\Controllers;

use App\Models\FleetObjective;
use App\Models\Truck;
use App\Models\TruckRestWindow;
use App\Services\FleetCapacityService;
use App\Services\FleetObjectiveService;
use App\Services\PlanningPeriodResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Objectives — the single authoring surface for multi-level planning objectives
 * (WEEK / MONTH / YEAR / CUSTOM). The list manages definition + lifecycle; the
 * create/edit page also owns the truck allocation (which trucks work vs rest and
 * the resulting redistribution) — the former "Planning flotte / fleet-roster"
 * capability, merged here so there is one place to plan an objective.
 *
 * Planned-vs-Actual / achievement KPIs live in the Planning Dashboard, not here.
 */
class FleetObjectiveController extends Controller
{
    public function __construct(
        private readonly FleetObjectiveService $objectives,
        private readonly PlanningPeriodResolver $periods,
        private readonly FleetCapacityService $capacity,
    ) {
        $this->middleware('permission:fleet-roster-plan');
    }

    public function index(Request $request)
    {
        $showArchived = $request->boolean('archived');

        $objectives = FleetObjective::with('creator:id,name')
            ->when(! $showArchived, fn ($q) => $q->active())
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        $rows = $objectives->map(fn (FleetObjective $o) => [
            'id' => $o->id,
            'period_type' => $o->period_type,
            'start_date' => $o->start_date->toDateString(),
            'end_date' => $o->end_date->toDateString(),
            'target_tons' => (float) $o->target_tons,
            'target_rotations' => (int) $o->target_rotations,
            'working_trucks' => $o->working_trucks,
            'notes' => $o->notes,
            'archived' => $o->archived_at !== null,
            'created_by' => $o->creator?->name,
        ])->values();

        return Inertia::render('logistics/objectives/Index', [
            'objectives' => $rows,
            'showArchived' => $showArchived,
            'periodTypes' => PlanningPeriodResolver::MODES,
        ]);
    }

    /**
     * The objective authoring page (create or edit): period + target + the truck
     * allocation (work/rest). Absorbs the old fleet-roster screen.
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

        $trucks = Truck::query()
            ->where('is_active', true)
            ->orderBy('matricule')
            ->get()
            ->map(function (Truck $t) {
                $info = $this->capacity->truckDailyCapacity($t);
                return [
                    'id' => $t->id,
                    'matricule' => $t->matricule,
                    'is_available' => (bool) $t->is_available,
                    'capacity_tonnage' => $info['capacity_tonnage'],
                    'target_weekly_capacity_t' => $info['target_weekly_capacity_t'],
                    'empirical_weekly_capacity_t' => $info['empirical_weekly_capacity_t'],
                ];
            })->values();

        // Trucks already rested for this exact period (so we pre-check them).
        $restedIds = TruckRestWindow::query()
            ->where('reason', TruckRestWindow::REASON_SURPLUS_CAPACITY)
            ->where('start_date', $start->toDateString())
            ->where('end_date', $end->toDateString())
            ->pluck('truck_id')
            ->all();

        return Inertia::render('logistics/objectives/Create', [
            'editing' => $editing ? ['id' => $editing->id] : null,
            'periodTypes' => PlanningPeriodResolver::MODES,
            'period' => ['type' => $periodType, 'start' => $start->toDateString(), 'end' => $end->toDateString()],
            'targetTons' => $targetTons,
            'notes' => $editing?->notes ?? '',
            'planCapacityT' => round($this->capacity->defaultCapacityTonnage(), 2),
            'trucks' => $trucks,
            'restedTruckIds' => $restedIds,
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
            'rested_truck_ids' => ['array'],
            'rested_truck_ids.*' => ['integer', 'exists:trucks,id'],
        ]);

        $p = $this->periods->resolve($data['period_type'], $data['start_date'], $data['start_date'], $data['end_date'] ?? null);
        $restedIds = array_values(array_unique($data['rested_truck_ids'] ?? []));
        $userId = $request->user()?->id;

        DB::transaction(function () use ($p, $restedIds, $userId, $data) {
            // Re-snapshot the surplus-capacity rest windows for this exact period.
            TruckRestWindow::query()
                ->where('reason', TruckRestWindow::REASON_SURPLUS_CAPACITY)
                ->where('start_date', $p['start']->toDateString())
                ->where('end_date', $p['end']->toDateString())
                ->delete();

            foreach ($restedIds as $truckId) {
                TruckRestWindow::create([
                    'truck_id' => $truckId,
                    'start_date' => $p['start']->toDateString(),
                    'end_date' => $p['end']->toDateString(),
                    'reason' => TruckRestWindow::REASON_SURPLUS_CAPACITY,
                    'notes' => $data['notes'] ?? 'Repos programmé : objectif assuré par flotte réduite.',
                    'created_by' => $userId,
                ]);
            }

            $this->objectives->upsert(
                $p['start'],
                $p['end'],
                (float) $data['target_tons'],
                $userId,
                $data['notes'] ?? null,
                $restedIds,
                $p['mode'],
            );
        });

        return redirect()->route('logistics.objectives.index')
            ->with('success', 'Objectif enregistré.');
    }

    public function archive(Request $request, FleetObjective $objective)
    {
        $objective->archived_at = $objective->archived_at ? null : now();
        $objective->save();

        return redirect()->route('logistics.objectives.index')
            ->with('success', $objective->archived_at ? 'Objectif archivé.' : 'Objectif réactivé.');
    }
}
