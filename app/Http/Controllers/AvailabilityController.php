<?php

namespace App\Http\Controllers;

use App\Models\TruckAvailabilityWindow;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Fleet availability — write surface for real downtime windows. The manager-facing
 * list now lives at /planning/availability (OperationsController, reading the shared
 * PlanningWorkspaceService); this controller owns only the window writes.
 */
class AvailabilityController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:fleet-roster-plan');
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
