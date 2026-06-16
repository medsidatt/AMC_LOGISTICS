<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DriverDisciplineRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DriverDisciplineController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:driver-discipline-view', ['only' => ['index']]);
        $this->middleware('permission:driver-discipline-manage', ['only' => ['store', 'destroy']]);
    }

    public function index(Driver $driver)
    {
        $records = $driver->disciplineRecords()
            ->with('recordedBy:id,name')
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (DriverDisciplineRecord $r) => [
                'id' => $r->id,
                'recorded_at' => $r->recorded_at?->format('Y-m-d'),
                'recorded_at_display' => $r->recorded_at?->format('d/m/Y'),
                'points' => $r->points,
                'reason' => $r->reason,
                'recorded_by' => $r->recordedBy?->name,
            ]);

        return Inertia::render('drivers/Discipline', [
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
            ],
            'records' => $records,
            'totals' => [
                'sum' => (int) $driver->disciplineRecords()->sum('points'),
                'count' => $driver->disciplineRecords()->count(),
            ],
        ]);
    }

    public function store(Request $request, Driver $driver)
    {
        $data = $request->validate([
            'recorded_at' => 'required|date',
            'points' => 'required|integer|min:-100|max:100',
            'reason' => 'required|string|max:500',
        ]);

        $driver->disciplineRecords()->create([
            'recorded_at' => $data['recorded_at'],
            'points' => $data['points'],
            'reason' => $data['reason'],
            'recorded_by' => auth()->id(),
        ]);

        return redirect()->route('drivers.discipline.index', $driver)
            ->with('success', 'Entrée discipline ajoutée.');
    }

    public function destroy(DriverDisciplineRecord $record)
    {
        $driverId = $record->driver_id;
        $record->delete();

        return redirect()->route('drivers.discipline.index', $driverId)
            ->with('success', 'Entrée supprimée.');
    }
}
