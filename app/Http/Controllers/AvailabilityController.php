<?php

namespace App\Http\Controllers;

use App\Models\Truck;
use App\Models\TruckAvailabilityWindow;
use App\Services\AvailabilityService;
use App\Services\FleetCapacityService;
use App\Services\OperationsCalendarService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Fleet availability — manage real downtime windows and view the resulting
 * available/lost capacity per truck and for the fleet. Read by the planning
 * engine; this is the manager-facing surface.
 */
class AvailabilityController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly FleetCapacityService $capacity,
        private readonly OperationsCalendarService $calendar,
    ) {
        $this->middleware('permission:fleet-roster-plan');
    }

    public function index(Request $request)
    {
        $anchor = $request->query('month') ? Carbon::parse($request->query('month')) : Carbon::now();
        $start = $anchor->copy()->startOfMonth();
        $end = $anchor->copy()->endOfMonth();

        $opDays = $this->calendar->operationalDays($start, $end);
        $opWeeks = $opDays / 6;
        $defaultRot = $this->capacity->defaultTargetRotationsPerWeek();

        $trucks = Truck::where('is_active', true)->orderBy('matricule')->get();

        // Planned rotations for the period = per-truck weekly target × operational weeks.
        $planned = [];
        foreach ($trucks as $t) {
            $rotPerWeek = $t->target_rotations_per_week ?? $defaultRot;
            $planned[$t->id] = round($rotPerWeek * $opWeeks, 2);
        }

        $fleet = $this->availability->forFleet($trucks, $start, $end->copy()->endOfDay(), $planned);

        $rows = $trucks->map(function (Truck $t) use ($fleet) {
            $a = $fleet['per_truck'][$t->id];
            return [
                'truck_id' => $t->id,
                'matricule' => $t->matricule,
                'operational_days' => $a['operational_days'],
                'lost_days' => $a['lost_days'],
                'availability_pct' => $a['availability_pct'],
                'available_capacity' => $a['available_capacity'],
                'lost_capacity' => $a['lost_capacity'],
                'source' => $a['source'],
            ];
        })->values();

        $windows = TruckAvailabilityWindow::query()
            ->with(['truck:id,matricule', 'creator:id,name'])
            ->overlapping($start, $end->copy()->endOfDay())
            ->orderByDesc('start_at')
            ->get()
            ->map(fn (TruckAvailabilityWindow $w) => [
                'id' => $w->id,
                'truck' => $w->truck?->matricule,
                'start_at' => $w->start_at->toDateString(),
                'end_at' => $w->end_at->toDateString(),
                'type' => $w->type,
                'reason' => $w->reason,
                'source' => $w->source,
                'created_by' => $w->creator?->name,
            ])->values();

        return Inertia::render('logistics/Availability', [
            'period' => [
                'anchor' => $start->toDateString(),
                'label' => $start->translatedFormat('F Y'),
                'operational_days' => $opDays,
            ],
            'fleet' => [
                'operational_capacity' => $fleet['operational_capacity'],
                'available_capacity' => $fleet['available_capacity'],
                'lost_capacity' => $fleet['lost_capacity'],
                'availability_pct' => $fleet['availability_pct'],
                'downtime_impact' => $fleet['downtime_impact'],
            ],
            'trucks' => $rows,
            'windows' => $windows,
            'truckOptions' => $trucks->map(fn (Truck $t) => ['value' => $t->id, 'label' => $t->matricule])->values(),
            'types' => TruckAvailabilityWindow::TYPES,
        ]);
    }

    public function storeWindow(Request $request)
    {
        $data = $request->validate([
            'truck_id' => ['required', 'exists:trucks,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'type' => ['required', 'in:' . implode(',', TruckAvailabilityWindow::TYPES)],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        TruckAvailabilityWindow::create([
            'truck_id' => $data['truck_id'],
            'start_at' => Carbon::parse($data['start_at'])->startOfDay(),
            'end_at' => Carbon::parse($data['end_at'])->endOfDay(),
            'type' => $data['type'],
            'reason' => $data['reason'] ?? null,
            'source' => TruckAvailabilityWindow::SOURCE_MANUAL,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Fenêtre de disponibilité enregistrée.');
    }

    public function destroyWindow(TruckAvailabilityWindow $window)
    {
        $window->delete();

        return back()->with('success', 'Fenêtre supprimée.');
    }
}
