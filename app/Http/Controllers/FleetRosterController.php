<?php

namespace App\Http\Controllers;

use App\Models\FleetObjective;
use App\Models\FleetObjectiveTruck;
use App\Models\TransportTracking;
use App\Models\Truck;
use App\Models\TruckRestWindow;
use App\Services\FleetCapacityService;
use App\Services\RotationAchievementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Lets the Logistics Responsible pick which trucks work for a given period
 * (e.g., a month) given the tonnage objective. Trucks not selected are put
 * on a "surplus_capacity" rest window for the whole period.
 */
class FleetRosterController extends Controller
{
    public function __construct(
        private readonly FleetCapacityService $capacity,
        private readonly RotationAchievementService $achievement,
    ) {
        $this->middleware('permission:fleet-roster-plan');
    }

    public function index(Request $request)
    {
        // Default period = the current work week, Monday → Saturday.
        $start = $request->query('start')
            ? Carbon::parse($request->query('start'))->startOfDay()
            : Carbon::now()->startOfWeek(Carbon::MONDAY);
        $end = $request->query('end')
            ? Carbon::parse($request->query('end'))->endOfDay()
            : Carbon::now()->startOfWeek(Carbon::MONDAY)->addDays(5)->endOfDay();

        $days = $start->diffInDays($end) + 1;
        $weeks = max(1, round($days / 7, 2));

        // Default target = the weekly target from the cascade × number of weeks
        $weeklyTarget = $this->capacity->resolveWeeklyTarget($start);
        $defaultTargetTons = round((float) $weeklyTarget['target_tons'] * $weeks, 2);

        $targetTons = $request->query('target_tons')
            ? (float) $request->query('target_tons')
            : $defaultTargetTons;

        $activeTrucks = Truck::query()
            ->where('is_active', true)
            ->orderBy('matricule')
            ->get()
            ->map(function (Truck $t) use ($weeks) {
                $info = $this->capacity->truckDailyCapacity($t);
                return [
                    'id' => $t->id,
                    'matricule' => $t->matricule,
                    'capacity_tonnage' => $info['capacity_tonnage'],
                    'target_rotations_per_week' => $info['target_rotations_per_week'],
                    'target_weekly_capacity_t' => $info['target_weekly_capacity_t'],
                    'period_capacity_t' => round($info['target_weekly_capacity_t'] * $weeks, 2),
                    'avg_rotations_per_week' => $info['avg_rotations_per_week'],
                    'empirical_weekly_capacity_t' => $info['empirical_weekly_capacity_t'],
                ];
            });

        // Suggested minimum trucks: assume average period capacity per truck
        $totalCapacityAllTrucks = (float) $activeTrucks->sum('period_capacity_t');
        $avgPerTruck = $activeTrucks->count() > 0 ? $totalCapacityAllTrucks / $activeTrucks->count() : 0;
        $minTrucksNeeded = $avgPerTruck > 0 ? (int) ceil($targetTons / $avgPerTruck) : 0;
        $minTrucksNeeded = max(1, min($minTrucksNeeded, $activeTrucks->count()));

        // Existing rest windows in the period (so we don't blindly overwrite)
        $existingRests = TruckRestWindow::query()
            ->where('reason', TruckRestWindow::REASON_SURPLUS_CAPACITY)
            ->where('start_date', $start->toDateString())
            ->where('end_date', $end->toDateString())
            ->pluck('truck_id')
            ->all();

        return Inertia::render('logistics/fleet-roster/Index', [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'days' => $days,
                'weeks' => $weeks,
            ],
            'objective' => [
                'target_tons' => $targetTons,
                'default_target_tons' => $defaultTargetTons,
                'weekly_target_tons' => (float) $weeklyTarget['target_tons'],
                'source' => $weeklyTarget['source'],
            ],
            'trucks' => $activeTrucks->values(),
            'total_capacity_t' => round($totalCapacityAllTrucks, 2),
            'min_trucks_needed' => $minTrucksNeeded,
            'avg_capacity_per_truck_t' => round($avgPerTruck, 2),
            'currently_rested_truck_ids' => $existingRests,
            'achievement' => $this->achievement->forPeriod($start, $end),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'rested_truck_ids' => 'array',
            'rested_truck_ids.*' => 'integer|exists:trucks,id',
            'notes' => 'nullable|string|max:500',
            'target_tons' => 'nullable|numeric|min:0',
        ]);

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $userId = auth()->id();
        $restedIds = array_values(array_unique($data['rested_truck_ids'] ?? []));

        DB::transaction(function () use ($start, $end, $restedIds, $userId, $data) {
            // Wipe previous surplus-capacity windows that exactly match this period
            TruckRestWindow::query()
                ->where('reason', TruckRestWindow::REASON_SURPLUS_CAPACITY)
                ->where('start_date', $start->toDateString())
                ->where('end_date', $end->toDateString())
                ->delete();

            foreach ($restedIds as $truckId) {
                TruckRestWindow::create([
                    'truck_id' => $truckId,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'reason' => TruckRestWindow::REASON_SURPLUS_CAPACITY,
                    'notes' => $data['notes'] ?? 'Repos programmé : objectif assuré par flotte réduite.',
                    'created_by' => $userId,
                ]);
            }

            $this->upsertObjective($start, $end, (float) ($data['target_tons'] ?? 0), $userId, $data['notes'] ?? null, $restedIds);
        });

        return redirect()
            ->route('logistics.fleet-roster.index', [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ])
            ->with('success', sprintf(
                'Programmation enregistrée : %d camion(s) au repos du %s au %s.',
                count($restedIds),
                $start->format('d/m/Y'),
                $end->format('d/m/Y'),
            ));
    }

    /**
     * Persist the objective for a period as soon as the user clicks "Appliquer",
     * without touching rest windows. Idempotent on (start_date, end_date).
     */
    public function apply(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'target_tons' => 'nullable|numeric|min:0',
        ]);

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        DB::transaction(function () use ($start, $end, $data) {
            // restedIds null → derived from existing rest windows for the period.
            $this->upsertObjective($start, $end, (float) ($data['target_tons'] ?? 0), auth()->id(), null);
        });

        return redirect()
            ->route('logistics.fleet-roster.index', [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'target_tons' => $data['target_tons'] ?? null,
            ])
            ->with('success', 'Objectif enregistré pour la période.');
    }

    /**
     * Upsert the FleetObjective header for a period (and, from Phase 2, its
     * per-truck target snapshot). When $restedIds is null the rested set is
     * derived from the existing surplus rest windows for the period.
     */
    private function upsertObjective(Carbon $start, Carbon $end, float $targetTons, ?int $userId, ?string $notes, ?array $restedIds = null): FleetObjective
    {
        if ($restedIds === null) {
            $restedIds = TruckRestWindow::query()
                ->where('reason', TruckRestWindow::REASON_SURPLUS_CAPACITY)
                ->where('start_date', $start->toDateString())
                ->where('end_date', $end->toDateString())
                ->pluck('truck_id')
                ->all();
        }

        $restedIds = array_values(array_unique($restedIds));
        $capacityPerRotation = max(0.01, $this->capacity->defaultCapacityTonnage());
        $targetRotations = (int) round($targetTons / $capacityPerRotation);
        $restedSet = array_flip($restedIds);

        $activeTrucks = Truck::where('is_active', true)->get(['id', 'capacity_tonnage', 'target_rotations_per_week']);
        $restedCount = count($restedIds);

        $objective = FleetObjective::updateOrCreate(
            ['start_date' => $start->toDateString(), 'end_date' => $end->toDateString()],
            [
                'target_tons' => $targetTons,
                'target_rotations' => $targetRotations,
                'working_trucks' => max(0, $activeTrucks->count() - $restedCount),
                'rested_trucks' => $restedCount,
                'notes' => $notes,
                'created_by' => $userId,
            ],
        );

        // Snapshot per-truck targets (frozen). Rested trucks get a 0-target row
        // so they still appear in the breakdown. Re-applying re-snapshots cleanly.
        $weeks = max(1.0, round(($start->diffInDays($end) + 1) / 7, 2));
        $objective->truckTargets()->delete();

        $now = now();
        $rows = $activeTrucks->map(function (Truck $truck) use ($objective, $restedSet, $weeks, $capacityPerRotation, $now) {
            $cap = (float) ($truck->capacity_tonnage ?: $capacityPerRotation);
            $rot = isset($restedSet[$truck->id]) ? 0 : (int) round($this->capacity->targetRotationsForTruck($truck) * $weeks);
            return [
                'fleet_objective_id' => $objective->id,
                'truck_id' => $truck->id,
                'target_rotations' => $rot,
                'target_tons' => round($rot * $cap, 2),
                'capacity_tonnage' => round($cap, 2),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        if ($rows) {
            FleetObjectiveTruck::insert($rows);
        }

        return $objective;
    }

    /**
     * Objective history per planning period: target vs achieved (effectuée)
     * and remaining (restante), in both tonnage and rotations. "Effectuée" is
     * measured from the delivered trips (client_net_weight / count) within the
     * objective's date range.
     */
    public function history()
    {
        $objectives = FleetObjective::with('creator:id,name')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit(26)
            ->get();

        $rows = $objectives->map(function (FleetObjective $o) {
            // Reconciled achievement (ticket + GPS) for the objective period.
            $a = $this->achievement->forPeriod($o->start_date->copy(), $o->end_date->copy())['fleet'];
            $targetTons = (float) $o->target_tons;

            return [
                'id' => $o->id,
                'start_date' => $o->start_date->format('d/m/Y'),
                'end_date' => $o->end_date->format('d/m/Y'),
                'target_tons' => $targetTons,
                'target_rotations' => $o->target_rotations,
                'achieved_tons' => $a['done_tons'],
                'achieved_rotations' => $a['done_rotations'],
                'ticketed_rotations' => $a['ticketed_rotations'],
                'gps_only_rotations' => $a['gps_only_rotations'],
                'missing_tickets' => $a['missing_tickets'],
                'remaining_tons' => $a['remaining_tons'],
                'remaining_rotations' => $a['remaining_rotations'],
                'pct' => $a['pct'],
                'working_trucks' => $o->working_trucks,
                'rested_trucks' => $o->rested_trucks,
                'notes' => $o->notes,
                'created_by' => $o->creator?->name,
            ];
        });

        // Chronological trend (oldest → newest) for the chart.
        $trend = $rows->reverse()->values()->map(fn ($r) => [
            'label' => $r['start_date'],
            'target_tons' => $r['target_tons'],
            'achieved_tons' => $r['achieved_tons'],
        ]);

        return Inertia::render('logistics/fleet-roster/History', [
            'objectives' => $rows,
            'trend' => $trend,
        ]);
    }
}
