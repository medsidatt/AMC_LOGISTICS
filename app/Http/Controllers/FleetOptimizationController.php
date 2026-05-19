<?php

namespace App\Http\Controllers;

use App\Models\ClientDemandPlan;
use App\Models\TruckAssignment;
use App\Models\TruckRestWindow;
use App\Services\FleetCapacityService;
use App\Services\FleetOptimizerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FleetOptimizationController extends Controller
{
    public function __construct(
        private readonly FleetCapacityService $capacity,
        private readonly FleetOptimizerService $optimizer,
    ) {
        $this->middleware('permission:fleet-optimization-view', ['only' => ['index', 'capacity', 'week']]);
        $this->middleware('permission:fleet-optimization-run', ['only' => ['run', 'updateAssignment', 'cancelAssignment']]);
    }

    public function index(Request $request)
    {
        $weekStart = $this->resolveWeekStart($request->query('week'));

        $capacity = $this->capacity->fleetWeeklyCapacity($weekStart);

        $assignments = TruckAssignment::query()
            ->with(['truck:id,matricule', 'driver:id,name', 'project:id,name,code', 'provider:id,name'])
            ->whereBetween('planned_date', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])
            ->orderBy('planned_date')
            ->orderBy('truck_id')
            ->get();

        $restWindows = TruckRestWindow::query()
            ->with(['truck:id,matricule'])
            ->where('start_date', '<=', $weekStart->copy()->addDays(6)->toDateString())
            ->where('end_date', '>=', $weekStart->toDateString())
            ->orderBy('start_date')
            ->get();

        $demands = ClientDemandPlan::query()
            ->with(['project:id,name,code', 'provider:id,name'])
            ->where('week_start_date', $weekStart->toDateString())
            ->orderBy('priority')
            ->get()
            ->map(function ($d) {
                return [
                    'id' => $d->id,
                    'project' => $d->project?->only(['id', 'name', 'code']),
                    'provider' => $d->provider?->only(['id', 'name']),
                    'client_name' => $d->client_name,
                    'required_tons' => (float) $d->required_tons,
                    'required_trucks' => $d->required_trucks,
                    'product' => $d->product,
                    'priority' => $d->priority,
                    'priority_label' => ClientDemandPlan::PRIORITY_LABELS[$d->priority] ?? '',
                    'allocated_tons' => (float) $d->allocated_tons,
                    'coverage_rate' => $d->coverage_rate,
                ];
            });

        return Inertia::render('logistics/optimization/Index', [
            'weekStart' => $weekStart->toDateString(),
            'weekEnd' => $weekStart->copy()->addDays(6)->toDateString(),
            'capacity' => $capacity,
            'demands' => $demands,
            'assignments' => $assignments->map(fn ($a) => [
                'id' => $a->id,
                'truck' => $a->truck?->only(['id', 'matricule']),
                'driver' => $a->driver?->only(['id', 'name']),
                'project' => $a->project?->only(['id', 'name', 'code']),
                'provider' => $a->provider?->only(['id', 'name']),
                'planned_date' => $a->planned_date->toDateString(),
                'planned_rotations' => $a->planned_rotations,
                'planned_tonnage' => (float) $a->planned_tonnage,
                'status' => $a->status,
                'client_demand_plan_id' => $a->client_demand_plan_id,
            ]),
            'restWindows' => $restWindows->map(fn ($r) => [
                'id' => $r->id,
                'truck' => $r->truck?->only(['id', 'matricule']),
                'start_date' => $r->start_date->toDateString(),
                'end_date' => $r->end_date->toDateString(),
                'reason' => $r->reason,
                'reason_label' => TruckRestWindow::REASON_LABELS[$r->reason] ?? $r->reason,
                'notes' => $r->notes,
            ]),
        ]);
    }

    public function capacity(Request $request)
    {
        $weekStart = $this->resolveWeekStart($request->query('week'));
        $capacity = $this->capacity->fleetWeeklyCapacity($weekStart);

        return Inertia::render('logistics/optimization/Capacity', [
            'weekStart' => $weekStart->toDateString(),
            'weekEnd' => $weekStart->copy()->addDays(6)->toDateString(),
            'capacity' => $capacity,
        ]);
    }

    public function run(Request $request)
    {
        $data = $request->validate([
            'week_start' => 'required|date',
            'dry_run' => 'sometimes|boolean',
        ]);

        $weekStart = Carbon::parse($data['week_start'])->startOfWeek(Carbon::MONDAY);
        $result = $this->optimizer->planWeek($weekStart, auth()->id(), $data['dry_run'] ?? false);

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return redirect()
            ->route('logistics.optimization.index', ['week' => $weekStart->toDateString()])
            ->with('success', sprintf(
                'Plan généré : %d affectation(s), %d repos planifié(s).',
                $result['summary']['assignments_count'],
                $result['summary']['rest_proposed_count']
            ));
    }

    public function updateAssignment(Request $request, TruckAssignment $assignment)
    {
        $data = $request->validate([
            'planned_rotations' => 'sometimes|integer|min:0',
            'planned_tonnage' => 'sometimes|numeric|min:0',
            'driver_id' => 'sometimes|nullable|exists:drivers,id',
            'status' => 'sometimes|in:' . implode(',', TruckAssignment::STATUSES),
            'notes' => 'sometimes|nullable|string',
        ]);

        $assignment->update($data);

        return redirect()->back()->with('success', 'Affectation mise à jour.');
    }

    public function cancelAssignment(TruckAssignment $assignment)
    {
        $assignment->update(['status' => TruckAssignment::STATUS_CANCELED]);
        return redirect()->back()->with('success', 'Affectation annulée.');
    }

    private function resolveWeekStart(?string $input): Carbon
    {
        $base = $input ? Carbon::parse($input) : Carbon::now();
        return $base->startOfWeek(Carbon::MONDAY);
    }
}
