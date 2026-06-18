<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Truck;
use App\Models\TruckDriverAssignment;
use App\Services\TruckDriverAssignmentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TruckDriverAssignmentController extends Controller
{
    public function __construct(private readonly TruckDriverAssignmentService $service)
    {
        $this->middleware('permission:driver-truck-assign');
    }

    public function index()
    {
        $trucks = Truck::query()
            ->where('is_active', true)
            ->with(['activeAssignments.driver:id,name'])
            ->orderBy('matricule')
            ->get(['id', 'matricule'])
            ->map(function (Truck $t) {
                $titulaire = $t->activeAssignments->firstWhere('role', TruckDriverAssignment::ROLE_TITULAIRE);
                $assistant = $t->activeAssignments->firstWhere('role', TruckDriverAssignment::ROLE_ASSISTANT);
                $slot = fn ($a) => $a ? ['assignment_id' => $a->id, 'driver_id' => $a->driver_id, 'name' => $a->driver?->name, 'since' => $a->started_at?->format('d/m/Y')] : null;

                return [
                    'id' => $t->id,
                    'matricule' => $t->matricule,
                    'titulaire' => $slot($titulaire),
                    'assistant' => $slot($assistant),
                    'parking' => $titulaire === null,
                ];
            })
            ->sortByDesc('parking')
            ->values();

        $assignedIds = TruckDriverAssignment::query()->whereNull('ended_at')->pluck('driver_id')->unique()->all();
        $available = Driver::query()
            ->where('is_active', true)
            ->whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Driver $d) => ['id' => $d->id, 'name' => $d->name])
            ->values();

        $history = TruckDriverAssignment::query()
            ->with(['truck:id,matricule', 'driver:id,name'])
            ->whereNotNull('ended_at')
            ->orderByDesc('ended_at')
            ->limit(50)
            ->get()
            ->map(fn (TruckDriverAssignment $a) => [
                'id' => $a->id,
                'truck' => $a->truck?->matricule,
                'driver' => $a->driver?->name,
                'role' => TruckDriverAssignment::ROLE_LABELS[$a->role] ?? $a->role,
                'started_at' => $a->started_at?->format('d/m/Y'),
                'ended_at' => $a->ended_at?->format('d/m/Y'),
            ]);

        return Inertia::render('logistics/affectations/Index', [
            'trucks' => $trucks,
            'availableDrivers' => $available,
            'history' => $history,
            'roles' => TruckDriverAssignment::ROLE_LABELS,
        ]);
    }

    public function assign(Request $request)
    {
        $data = $request->validate([
            'truck_id' => 'required|exists:trucks,id',
            'driver_id' => 'required|exists:drivers,id',
            'role' => 'required|in:' . implode(',', array_keys(TruckDriverAssignment::ROLE_LABELS)),
        ]);

        $truck = Truck::findOrFail($data['truck_id']);
        $driver = Driver::findOrFail($data['driver_id']);

        // Was the driver already on a different truck? (for a clearer message)
        $previous = $driver->activeAssignment()->with('truck:id,matricule')->first();

        $this->service->assign($truck, $driver, $data['role'], auth()->id());

        $message = sprintf('%s affecté au camion %s (%s).', $driver->name, $truck->matricule, TruckDriverAssignment::ROLE_LABELS[$data['role']]);
        if ($previous && $previous->truck_id !== $truck->id) {
            $message .= sprintf(' Déplacé depuis %s.', $previous->truck?->matricule ?? '—');
        }

        return back()->with('success', $message);
    }

    public function release(Request $request)
    {
        $data = $request->validate([
            'assignment_id' => 'required|exists:truck_driver_assignments,id',
        ]);

        $assignment = TruckDriverAssignment::findOrFail($data['assignment_id']);
        $this->service->release($assignment, auth()->id());

        return back()->with('success', 'Chauffeur libéré — camion au parking.');
    }
}
