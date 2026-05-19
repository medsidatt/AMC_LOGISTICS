<?php

namespace App\Http\Controllers;

use App\Models\Truck;
use App\Models\TruckRestWindow;
use App\Services\FleetCapacityService;
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
    ) {
        $this->middleware('permission:fleet-roster-plan');
    }

    public function index(Request $request)
    {
        $start = $request->query('start')
            ? Carbon::parse($request->query('start'))->startOfDay()
            : Carbon::now()->startOfMonth();
        $end = $request->query('end')
            ? Carbon::parse($request->query('end'))->endOfDay()
            : Carbon::now()->endOfMonth();

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
        ]);

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $userId = auth()->id();
        $restedIds = array_unique($data['rested_truck_ids'] ?? []);

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
}
