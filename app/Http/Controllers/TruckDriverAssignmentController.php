<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Truck;
use App\Models\TruckDriverAssignment;
use App\Services\TruckDriverAssignmentService;
use Illuminate\Http\Request;

class TruckDriverAssignmentController extends Controller
{
    public function __construct(
        private readonly TruckDriverAssignmentService $service,
    ) {
        $this->middleware('permission:driver-truck-assign');
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
